<?php

namespace App\Promotions;

use App\Models\PromoCode;
use App\Support\Money;

/**
 * Prix d'une formation avec un code promo appliqué.
 */
final readonly class PromoQuote
{
    public function __construct(
        public PromoCode $code,
        public int $originalAmount,
        public int $discount,
    ) {}

    public function amount(): int
    {
        return $this->originalAmount - $this->discount;
    }

    public function formattedAmount(): string
    {
        return Money::fcfa($this->amount());
    }
}
