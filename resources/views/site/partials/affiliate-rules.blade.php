{{-- Règles du programme d'affiliation (valeurs lues dans config/affiliate.php). --}}
@php
    $rules = [
        'Commission de '.config('affiliate.rate').' % du prix payé sur chaque achat validé passé par votre lien : formations et suppléments de sessions en présentiel.',
        'Un clic sur votre lien reste valable '.config('affiliate.cookie_days').' jours. Si la personne clique ensuite sur le lien d\'un autre affilié, c\'est le dernier lien cliqué qui compte.',
        'Aucune commission sur vos propres achats.',
        'La commission est créée quand notre équipe valide le paiement (jamais pendant un accès provisoire) et devient disponible '.config('affiliate.availability_days').' jours plus tard.',
        'Elle est annulée si l\'achat est refusé ou remboursé avant d\'être versée. Si l\'achat est remboursé après, le montant est déduit de vos prochaines commissions.',
        'Retrait par Mobile Money à partir de '.\App\Support\Money::fcfa((int) config('affiliate.min_withdrawal')).' de solde, une demande à la fois. Les frais d\'envoi Mobile Money sont déduits du montant retiré.',
        'Toute fraude (auto-parrainage, faux comptes, fausses commandes, envois massifs non sollicités) entraîne l\'annulation des commissions concernées et peut entraîner la suspension du compte affilié.',
    ];
@endphp

<ul class="{{ $class ?? 'space-y-3' }}">
    @foreach ($rules as $rule)
        <li class="flex items-start gap-3 text-sm text-slate-300">
            <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-gold-400/15 text-gold-300"><flux:icon.check class="size-3.5" /></span>
            <span>{{ $rule }}</span>
        </li>
    @endforeach
</ul>
