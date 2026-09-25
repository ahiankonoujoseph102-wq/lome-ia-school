<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\PromoCode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * « Codes promo » (admin uniquement) : création, modification, activation et statistiques
 * (utilisations, chiffre d'affaires des paiements validés, remises accordées).
 */
class PromoCodeController extends Controller
{
    public function index(): View
    {
        $codes = PromoCode::query()
            ->with('formations:id,title')
            ->withCount('uses')
            ->withSum(['purchases as revenue' => fn ($query) => $query->where('status', PurchaseStatus::Validated)], 'amount')
            ->withSum(['purchases as discounts' => fn ($query) => $query->where('status', PurchaseStatus::Validated)], 'discount_amount')
            ->latest()
            ->get();

        return view('site.admin.promo-codes.index', ['codes' => $codes]);
    }

    public function create(): View
    {
        return view('site.admin.promo-codes.form', [
            'promo' => new PromoCode(['type' => 'percent', 'max_uses_per_user' => 1]),
            'formations' => Formation::query()->orderBy('sort_order')->get(['id', 'title', 'price']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $promo = new PromoCode;
        $promo->created_by = $request->user()?->id;
        $this->fill($promo, $request);

        ActivityLog::record(AdminAction::PromoCodeCreated, 'Code promo créé : '.$promo->code.' ('.$promo->valueLabel().').', $promo);

        return to_route('admin.promo-codes.index')->with('status', 'Code promo « '.$promo->code.' » créé.');
    }

    public function edit(PromoCode $promoCode): View
    {
        return view('site.admin.promo-codes.form', [
            'promo' => $promoCode->load('formations:id'),
            'formations' => Formation::query()->orderBy('sort_order')->get(['id', 'title', 'price']),
        ]);
    }

    public function update(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $this->fill($promoCode, $request);

        ActivityLog::record(AdminAction::PromoCodeUpdated, 'Code promo modifié : '.$promoCode->code.' ('.$promoCode->valueLabel().', '.mb_strtolower($promoCode->stateLabel()).').', $promoCode);

        return to_route('admin.promo-codes.index')->with('status', 'Code promo « '.$promoCode->code.' » enregistré.');
    }

    public function toggle(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->forceFill(['is_active' => ! $promoCode->is_active])->save();

        ActivityLog::record(AdminAction::PromoCodeUpdated, 'Code promo '.$promoCode->code.' '.($promoCode->is_active ? 'activé' : 'désactivé').'.', $promoCode);

        return back()->with('status', 'Code promo « '.$promoCode->code.' » '.($promoCode->is_active ? 'activé.' : 'désactivé.'));
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        if ($promoCode->purchases()->exists()) {
            return back()->withErrors(['promo' => 'Ce code a déjà été utilisé : désactivez-le plutôt, pour garder ses statistiques.']);
        }

        $promoCode->delete();
        ActivityLog::record(AdminAction::PromoCodeDeleted, 'Code promo supprimé : '.$promoCode->code.'.');

        return to_route('admin.promo-codes.index')->with('status', 'Code promo supprimé.');
    }

    private function fill(PromoCode $promo, Request $request): void
    {
        $request->merge(['code' => PromoCode::normalize($request->string('code')->toString())]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promo_codes', 'code')->ignore($promo->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'type' => ['required', Rule::in(array_keys(PromoCode::TYPES))],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:99' : 'max:100000000'],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'all_formations' => ['required', 'boolean'],
            'formations' => [Rule::requiredIf(! $request->boolean('all_formations')), 'array'],
            'formations.*' => ['integer', 'exists:formations,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Le code ne contient que des lettres, des chiffres, - et _ (sans espace).',
            'code.unique' => 'Ce code existe déjà.',
            'value.max' => $request->input('type') === 'percent' ? 'Un pourcentage va de 1 à 99 %.' : 'Montant trop élevé.',
            'ends_at.after' => 'La date de fin doit être après la date de début.',
            'formations.required' => 'Choisissez au moins une formation, ou « Toutes les formations ».',
        ]);

        $promo->fill([
            'code' => $validated['code'],
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'type' => $validated['type'],
            'value' => (int) $validated['value'],
            'starts_at' => filled($validated['starts_at'] ?? null) ? CarbonImmutable::parse($validated['starts_at']) : null,
            'ends_at' => filled($validated['ends_at'] ?? null) ? CarbonImmutable::parse($validated['ends_at']) : null,
            'max_uses' => $validated['max_uses'] ?? null,
            'max_uses_per_user' => $validated['max_uses_per_user'] ?? null,
            'all_formations' => $request->boolean('all_formations'),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $promo->formations()->sync($promo->all_formations ? [] : array_map('intval', $validated['formations'] ?? []));
    }
}
