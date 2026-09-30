@php
    use App\Enums\PurchaseStatus;

    $status = $purchase->status;
    [$icon, $title, $tone] = match ($status) {
        PurchaseStatus::Provisional => ['lock-open', $purchase->isSessionSupplement() ? 'Paiement reçu, place confirmée' : 'Paiement reçu, formation ouverte', 'text-sky-300 bg-sky-400/15'],
        PurchaseStatus::Pending => ['clock', 'Paiement reçu, en attente de validation', 'text-gold-300 bg-gold-400/15'],
        PurchaseStatus::Validated => ['check-badge', 'Paiement validé', 'text-emerald-300 bg-emerald-400/15'],
        PurchaseStatus::Rejected => ['x-circle', 'Paiement refusé', 'text-rose-300 bg-rose-400/15'],
        PurchaseStatus::Refunded => ['arrow-uturn-left', 'Achat remboursé', 'text-slate-300 bg-veil/10'],
    };
@endphp

<x-layouts::site title="Mon achat" variant="student">
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <a href="{{ route('student.purchases.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Mes achats
        </a>

        @if (session('status'))
            <x-site.alert class="mt-4">{{ session('status') }}</x-site.alert>
        @endif

        <div class="card mt-4 p-6 text-center sm:p-8">
            <span class="mx-auto grid size-16 place-items-center rounded-3xl {{ $tone }}">
                <flux:icon :icon="$icon" class="size-8" />
            </span>
            <h1 class="mt-5 text-2xl font-bold text-balance text-white">{{ $title }}</h1>
            <p class="mx-auto mt-3 max-w-md text-slate-300">@include('site.student.purchases.status-message')</p>

            <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                @if ($status->grantsAccess())
                    <a href="{{ $purchase->isSessionSupplement() && $purchase->liveSession ? route('student.sessions.show', $purchase->liveSession) : route('student.formations.show', $purchase->formation) }}" class="btn-gold" data-test="open-access">{{ $purchase->isSessionSupplement() ? 'Voir la session' : ($status === PurchaseStatus::Provisional ? 'Commencer la formation' : 'Ouvrir la formation') }} <flux:icon.arrow-right class="size-5" /></a>
                @endif
                @if ($status === PurchaseStatus::Validated)
                    <a href="{{ route('student.purchases.receipt', $purchase) }}" class="btn-ghost"><flux:icon.document-text class="size-5" /> Voir mon reçu</a>
                @endif
                @if ($status === PurchaseStatus::Rejected)
                    <a href="{{ \App\Support\WhatsApp::url('Bonjour Lomé IA School, mon paiement '.$purchase->receipt_number.' a été refusé. Pouvez-vous m\'aider ?') }}" target="_blank" rel="noopener" class="btn-ghost">
                        <x-site.whatsapp-icon class="size-5 text-[#25D366]" /> Nous contacter
                    </a>
                @endif
            </div>
        </div>

        <section class="card mt-6 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Détails du paiement</h2>
            <dl class="mt-4 divide-y divide-veil/10 text-sm">
                @foreach (array_filter([
                    $purchase->itemHeading() => $purchase->item_label,
                    'Prix avant remise' => $purchase->discount_amount ? \App\Support\Money::fcfa((int) $purchase->original_amount) : null,
                    'Remise (code promo)' => $purchase->discount_amount ? '-'.\App\Support\Money::fcfa($purchase->discount_amount).($purchase->promoCode ? ' · '.$purchase->promoCode->code : '') : null,
                    'Montant' => $purchase->formatted_amount,
                    'Moyen de paiement' => collect([$purchase->payment_method_name, $purchase->payment_number])->filter()->implode(' · '),
                    'Bénéficiaire' => $purchase->payment_beneficiary,
                    'Numéro qui a payé' => $purchase->payer_phone,
                    'Nom de l\'expéditeur' => $purchase->payer_name,
                    'Identifiant de transaction' => $purchase->transaction_id,
                    'Envoyé le' => $purchase->created_at?->translatedFormat('j F Y à H\hi'),
                    'Référence' => $purchase->receipt_number,
                ]) as $label => $value)
                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2.5">
                        <dt class="text-slate-400">{{ $label }}</dt>
                        <dd class="font-medium break-all text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($purchase->proof_path)
                <a href="{{ route('student.purchases.proof', $purchase) }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-electric-300 hover:text-white">
                    <flux:icon.photo class="size-4" /> Voir la capture envoyée
                </a>
            @endif
        </section>
    </div>
</x-layouts::site>
