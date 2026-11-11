<?php

namespace App\Scholarships;

use App\Mail\ScholarshipMail;
use App\Models\Purchase;
use App\Models\ScholarshipApplication;
use App\Payments\PaymentMethods;
use App\Payments\ProofImageProcessor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Candidature à la bourse, sans compte : réponses du formulaire et capture du paiement des frais d'inscription.
 *
 * Contrôles, sous un verrou (deux personnes ne prennent jamais la même dernière place) : bourse ouverte, place
 * libre pour ce genre, une seule candidature par adresse e-mail et par numéro WhatsApp, capture jamais envoyée
 * (ni pour une autre candidature, ni pour un achat). La place est réservée ; aucun accès n'est ouvert : Joseph
 * vérifie le paiement dans son historique Mobile Money, puis valide ou refuse (ScholarshipDecisions).
 */
class ScholarshipSubmitter
{
    public const string PROOF_FOLDER = 'bourses';

    public function __construct(private ProofImageProcessor $proofs) {}

    /**
     * @param  array<string, mixed>  $answers  réponses déjà vérifiées par le contrôleur (téléphone normalisé)
     */
    public function submit(array $answers, UploadedFile $proof): ScholarshipApplication
    {
        $method = PaymentMethods::findEnabled((string) $answers['payment_method']);

        if (! $method) {
            throw ValidationException::withMessages(['payment_method' => 'Choisissez le moyen de paiement que vous avez utilisé.']);
        }

        $hash = $this->proofs->hash($proof);
        $this->ensureCanApply($answers, $hash);
        $path = $this->proofs->store($proof, self::PROOF_FOLDER);

        try {
            $application = Cache::lock('scholarship:'.ScholarshipCampaign::campaign(), 15)->block(10, function () use ($answers, $hash, $path, $method): ScholarshipApplication {
                return DB::transaction(function () use ($answers, $hash, $path, $method): ScholarshipApplication {
                    $this->ensureCanApply($answers, $hash);

                    $application = new ScholarshipApplication([
                        ...array_intersect_key($answers, array_flip(['name', 'phone', 'email', 'gender', 'age', 'city', 'level', 'tools', 'device', 'motivation', 'first_agent', 'available'])),
                        'campaign' => ScholarshipCampaign::campaign(),
                        'amount' => ScholarshipCampaign::fee(),
                        'payment_method' => $method->key,
                        'payment_method_name' => $method->name,
                        'payment_number' => $method->number,
                        'country' => $method->country,
                        'proof_path' => $path,
                        'proof_hash' => $hash,
                    ]);
                    $application->status = ScholarshipApplication::RESERVED;
                    $application->save();

                    return $application;
                });
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk(ProofImageProcessor::DISK)->delete($path);

            throw ValidationException::withMessages(['proof' => 'Cette capture d\'écran a déjà été envoyée. Envoie la capture de ton propre paiement.']);
        } catch (Throwable $exception) {
            Storage::disk(ProofImageProcessor::DISK)->delete($path);

            throw $exception;
        }

        // Accusé de réception, envoyé juste après la réponse (jamais bloquant pour la candidature).
        defer(function () use ($application): void {
            try {
                Mail::to($application->email)->send(new ScholarshipMail($application, ScholarshipMail::RECEIVED));
            } catch (Throwable $exception) {
                Log::warning('Bourse : accusé de réception non envoyé.', ['application' => $application->id, 'error' => $exception->getMessage()]);
            }
        });

        return $application;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function ensureCanApply(array $answers, string $hash): void
    {
        $state = ScholarshipCampaign::state();

        if ($state !== ScholarshipCampaign::OPEN) {
            throw ValidationException::withMessages(['form' => match ($state) {
                ScholarshipCampaign::FULL => 'Les '.ScholarshipCampaign::totalSeats().' places sont prises : les candidatures sont closes.',
                ScholarshipCampaign::SOON => 'Les candidatures ne sont pas encore ouvertes.',
                default => 'Candidatures closes.',
            }]);
        }

        $gender = (string) $answers['gender'];

        if ((ScholarshipCampaign::remaining()[$gender] ?? 0) <= 0) {
            throw ValidationException::withMessages(['gender' => 'Il n\'y a plus de place pour les '.($gender === 'female' ? 'femmes' : 'hommes').'. Une place peut se libérer : reviens voir le compteur un peu plus tard.']);
        }

        $existing = ScholarshipApplication::query()
            ->where('campaign', ScholarshipCampaign::campaign())
            ->holdingSeat()
            ->where(fn ($query) => $query->where('email', $answers['email'])->orWhere('phone', $answers['phone']))
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                $existing->email === $answers['email'] ? 'email' : 'phone' => 'Une candidature a déjà été envoyée avec '.($existing->email === $answers['email'] ? 'cette adresse e-mail' : 'ce numéro WhatsApp').'. Une seule candidature par personne.',
            ]);
        }

        if (ScholarshipApplication::query()->where('proof_hash', $hash)->exists() || Purchase::query()->where('proof_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['proof' => 'Cette capture d\'écran a déjà été envoyée. Envoie la capture de ton propre paiement.']);
        }
    }
}
