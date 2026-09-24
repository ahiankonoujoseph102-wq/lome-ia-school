@php
    use App\Support\Placeholder;

    $legal = config('site.legal');
    $contact = config('site.contact');
@endphp

<x-layouts::site title="Conditions générales de vente" description="Conditions générales de vente des formations Lomé IA School.">
    @include('site.partials.page-header', ['title' => 'Conditions générales de vente'])

    <section class="mx-auto max-w-3xl px-4 pb-20 sm:px-6">
        <div class="prose-site card p-6 sm:p-10">
            <h2>1. Objet</h2>
            <p>Les présentes conditions régissent la vente des formations proposées par {{ $legal['company_name'] }} sur ce site : formations enregistrées (vidéos en ligne), formations en direct (Google Meet) et formations en présentiel à Lomé. Tout achat implique l'acceptation de ces conditions.</p>

            <h2>2. Prix</h2>
            <p>Les prix sont indiqués en francs CFA (FCFA / XOF) sur la page de chaque formation. Le prix applicable est celui affiché au moment de la commande.</p>

            <h2>3. Commande et paiement</h2>
            <p>La commande nécessite la création d'un compte élève. Le paiement s'effectue en ligne par Mobile Money (T-Money / Yas, Flooz / Moov) via la plateforme de paiement sécurisée FedaPay. {{ $legal['company_name'] }} n'a pas accès à vos identifiants Mobile Money.</p>

            <h2>4. Accès à la formation</h2>
            <p>L'accès à la formation et à son groupe privé de discussion est ouvert dès la confirmation du paiement, depuis l'espace élève.</p>
            <p><strong class="text-white">Accès à vie et accompagnement à vie.</strong> {{ Placeholder::highlight($legal['access_duration']) }}</p>
            <p>Pour les formations en direct, les liens des sessions sont communiqués aux inscrits. Pour les formations en présentiel, les dates et le lieu sont communiqués aux inscrits.</p>

            <h2>5. Usage personnel</h2>
            <p>L'accès à une formation est strictement personnel. Le partage des identifiants, l'enregistrement, la copie ou la revente des contenus sont interdits et peuvent entraîner la suspension de l'accès sans remboursement.</p>

            <h2>6. Groupes de discussion</h2>
            <p>Chaque formation dispose d'un groupe privé réservé à ses élèves. Les échanges doivent rester respectueux et en lien avec la formation. {{ $legal['company_name'] }} peut retirer tout message ou membre ne respectant pas ces règles.</p>

            <h2>7. Remboursement</h2>
            <p>{{ Placeholder::highlight($legal['refund_policy']) }}</p>

            <h2>8. Programme d'affiliation</h2>
            <p>Les élèves peuvent recommander les formations grâce à un lien personnel et percevoir une commission de {{ config('site.affiliate.rate') }} % du prix payé pour chaque vente réalisée grâce à ce lien. Les demandes de retrait par Mobile Money sont vérifiées puis validées par {{ $legal['company_name'] }}. Toute fraude (auto-parrainage, fausses commandes…) entraîne l'annulation des commissions concernées.</p>

            <h2>9. Contact</h2>
            <p>Pour toute question ou demande de remboursement : <a href="mailto:{{ $legal['owner']['email'] }}">{{ $legal['owner']['email'] }}</a> ou {{ $legal['owner']['phone'] }} (appel ou WhatsApp), {{ mb_lcfirst($contact['hours']) }}.</p>

            <p class="pt-6 text-sm text-slate-500">Dernière mise à jour : {{ Placeholder::highlight($legal['updated_at']) }}</p>
        </div>
    </section>
</x-layouts::site>
