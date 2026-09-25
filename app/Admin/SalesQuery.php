<?php

namespace App\Admin;

use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filtres des ventes (liste « Toutes les ventes » et export CSV) : dates, statut, formation,
 * type (formation ou supplément), mode (Mobile Money ou accès manuel), code promo.
 */
class SalesQuery
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(PurchaseStatus::class)],
            'formation' => ['nullable', 'integer'],
            'kind' => ['nullable', Rule::in(['formation', 'supplement'])],
            'gateway' => ['nullable', Rule::enum(PaymentGateway::class)],
            'code' => ['nullable', 'integer'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Purchase>
     */
    public static function build(array $filters): Builder
    {
        return Purchase::query()
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('created_at', '>=', CarbonImmutable::parse((string) $from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('created_at', '<=', CarbonImmutable::parse((string) $to)->endOfDay()))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['formation'] ?? null, fn (Builder $query, $formation) => $query->where('formation_id', (int) $formation))
            ->when(($filters['kind'] ?? null) === 'formation', fn (Builder $query) => $query->whereNull('live_session_id'))
            ->when(($filters['kind'] ?? null) === 'supplement', fn (Builder $query) => $query->whereNotNull('live_session_id'))
            ->when($filters['gateway'] ?? null, fn (Builder $query, $gateway) => $query->where('gateway', $gateway))
            ->when($filters['code'] ?? null, fn (Builder $query, $code) => $query->where('promo_code_id', (int) $code));
    }
}
