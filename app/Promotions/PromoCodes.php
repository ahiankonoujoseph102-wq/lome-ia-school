<?php

namespace App\Promotions;

use App\Models\Formation;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Vérification d'un code promo pour une formation : actif, dans ses dates, formation concernée,
 * nombre maximum d'utilisations (au total et par élève) pas atteint. Erreurs sur le champ « promo_code ».
 */
class PromoCodes
{
    /**
     * @param  bool  $lock  verrouiller la ligne du code (au moment du paiement, pour ne jamais dépasser la limite)
     */
    public function quote(?string $input, Formation $formation, ?User $user, bool $lock = false): PromoQuote
    {
        $code = PromoCode::normalize($input);

        $promo = $code === '' ? null : PromoCode::query()
            ->where('code', $code)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with('formations:id')
            ->first();

        if ($promo === null || ! $promo->is_active) {
            self::fail('Ce code promo n\'existe pas ou n\'est plus actif.');
        }

        if ($promo->starts_at?->isFuture()) {
            self::fail('Ce code promo sera valable à partir du '.$promo->starts_at->translatedFormat('j F Y à H\hi').'.');
        }

        if ($promo->ends_at?->isPast()) {
            self::fail('Ce code promo a expiré le '.$promo->ends_at->translatedFormat('j F Y').'.');
        }

        if (! $promo->appliesTo($formation)) {
            self::fail('Ce code promo ne s\'applique pas à cette formation.');
        }

        if ($promo->max_uses !== null && $promo->uses()->count() >= $promo->max_uses) {
            self::fail('Ce code promo a déjà été utilisé le nombre de fois prévu.');
        }

        if ($user !== null && $promo->max_uses_per_user !== null && $promo->uses()->where('user_id', $user->id)->count() >= $promo->max_uses_per_user) {
            self::fail('Vous avez déjà utilisé ce code promo.');
        }

        $discount = $promo->discountFor($formation->price);

        if ($discount <= 0 || $discount >= $formation->price) {
            self::fail('Ce code promo ne peut pas s\'appliquer à cette formation.');
        }

        return new PromoQuote($promo, $formation->price, $discount);
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['promo_code' => $message]);
    }
}
