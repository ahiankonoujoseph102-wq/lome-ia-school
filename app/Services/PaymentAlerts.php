<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use Illuminate\Support\Str;

/**
 * Signaux d'alerte affichés à l'admin à côté d'un paiement à vérifier. Ce sont des indices, pas des preuves.
 */
class PaymentAlerts
{
    /**
     * @return list<array{level: string, text: string}>
     */
    public function for(Purchase $purchase): array
    {
        $alerts = [];
        $user = $purchase->user;

        if ($purchase->status === PurchaseStatus::Pending) {
            $alerts[] = ['level' => 'info', 'text' => 'Montant élevé : aucun accès tant que vous n\'avez pas validé.'];
        }

        if (! $user) {
            return $alerts;
        }

        if ($purchase->payer_name && ! $this->namesMatch($purchase->payer_name, $user->name)) {
            $alerts[] = ['level' => 'warning', 'text' => 'Le nom de l\'expéditeur (« '.$purchase->payer_name.' ») ne correspond pas au nom du compte (« '.$user->name.' »).'];
        }

        if ($user->created_at && $purchase->created_at && $user->created_at->diffInMinutes($purchase->created_at) < 60) {
            $alerts[] = ['level' => 'info', 'text' => 'Compte créé moins d\'une heure avant le paiement.'];
        }

        $rejected = $user->purchases()->where('status', PurchaseStatus::Rejected)->count();

        if ($rejected > 0) {
            $alerts[] = ['level' => 'danger', 'text' => $rejected.' paiement'.($rejected > 1 ? 's' : '').' déjà refusé'.($rejected > 1 ? 's' : '').' pour cet élève.'];
        }

        // Numéro payeur : seulement sur les anciens paiements (l'élève n'envoie plus que la capture).
        $samePhoneElsewhere = filled($purchase->payer_phone) && Purchase::query()
            ->where('payer_phone', $purchase->payer_phone)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($samePhoneElsewhere) {
            $alerts[] = ['level' => 'warning', 'text' => 'Ce numéro payeur a déjà été utilisé par un autre élève.'];
        }

        return $alerts;
    }

    /**
     * Au moins un mot (de 2 lettres ou plus) en commun, sans tenir compte des accents ni de la casse.
     */
    private function namesMatch(string $a, string $b): bool
    {
        $words = fn (string $name): array => array_filter(
            preg_split('/[^a-z]+/', Str::lower(Str::ascii($name))) ?: [],
            fn (string $word): bool => strlen($word) >= 2,
        );

        return array_intersect($words($a), $words($b)) !== [];
    }
}
