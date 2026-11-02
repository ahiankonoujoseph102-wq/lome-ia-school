<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\QuoteRequestMail;
use App\Models\QuoteRequest;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Formulaire « Demander un devis » de la page « Entreprises et réalisations sur mesure » (#devis).
 * Sans compte ; champ piège « website » (réponse neutre, rien d'enregistré) ; 5 envois par 10 minutes et par
 * adresse IP (route). La demande est enregistrée, puis envoyée par e-mail à l'admin juste après la réponse.
 */
class QuoteRequestController extends Controller
{
    public const string SENT = 'Merci, votre demande est envoyée. Nous vous répondons par e-mail ou sur WhatsApp dès que possible.';

    public function store(Request $request): RedirectResponse
    {
        $back = redirect()->to(route('business').'#devis');

        if (filled($request->input('website'))) {
            return $back->with('quote_sent', self::SENT);
        }

        try {
            $validated = $this->validated($request);
        } catch (ValidationException $exception) {
            // Retour directement sur le formulaire (#devis), pas en haut de la page.
            throw $exception->redirectTo(route('business').'#devis');
        }

        $phone = trim((string) ($validated['phone'] ?? ''));

        if ($phone !== '') {
            $normalized = PhoneNumber::normalize($phone);

            if ($normalized === null) {
                throw ValidationException::withMessages(['phone' => 'Numéro WhatsApp invalide : écrivez-le avec l\'indicatif du pays (ex. +228 90 00 00 00), ou laissez le champ vide.'])->redirectTo(route('business').'#devis');
            }

            $phone = $normalized;
        }

        $quote = QuoteRequest::query()->create([
            'name' => trim((string) $validated['name']),
            'organization' => filled($validated['organization'] ?? null) ? trim((string) $validated['organization']) : null,
            'email' => trim((string) $validated['email']),
            'phone' => $phone !== '' ? $phone : null,
            'service' => (string) $validated['service'],
            'message' => trim((string) $validated['message']),
        ]);

        // Envoyé juste après la réponse (comme les demandes transmises par Ama), jamais par la file d'attente.
        defer(function () use ($quote): void {
            try {
                Mail::to((string) config('site.payments.admin_email'))->send(new QuoteRequestMail($quote));
            } catch (Throwable $exception) {
                Log::warning('Demande de devis : e-mail non envoyé.', ['quote' => $quote->id, 'error' => $exception->getMessage()]);
            }
        });

        return $back->with('quote_sent', self::SENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organization' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email:rfc,filter', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'service' => ['required', Rule::in(array_keys((array) config('site.custom_work.services')))],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'name.required' => 'Indiquez votre nom.',
            'email.required' => 'Indiquez votre adresse e-mail pour recevoir la réponse.',
            'email.email' => 'Cette adresse e-mail ne semble pas valide.',
            'service.required' => 'Choisissez ce dont vous avez besoin.',
            'service.in' => 'Choisissez ce dont vous avez besoin.',
            'message.required' => 'Décrivez votre projet en quelques lignes.',
            'message.min' => 'Décrivez votre projet en quelques lignes (10 caractères au moins).',
        ]);
    }
}
