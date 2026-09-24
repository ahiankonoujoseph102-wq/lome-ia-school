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
            <p>Les présentes conditions régissent la vente des formations proposées par {{ $legal['company_name'] }} sur ce site. Toutes les formations sont enregistrées (vidéos en ligne). Des sessions ponctuelles en direct (Google Meet) ou en présentiel à Lomé peuvent être organisées de temps en temps ; elles sont annoncées sur le site. Tout achat implique l'acceptation de ces conditions.</p>

            <h2>2. Prix</h2>
            <p>Les prix sont indiqués en francs CFA (FCFA / XOF) sur la page de chaque formation. Le prix applicable est celui affiché au moment de la commande.</p>

            <h2>3. Commande et paiement</h2>
            <p>La commande nécessite la création d'un compte élève. Le paiement s'effectue par Mobile Money, en envoyant le montant sur l'un des numéros indiqués sur la page de commande (Togo, Burkina Faso ou Bénin). Au Burkina Faso et au Bénin, le paiement est reçu par un représentant de {{ $legal['company_name'] }}.</p>
            <p>{{ config('site.payments.fees_notice') }} Le montant reçu doit être exactement égal au prix de la formation.</p>
            <p>Après le paiement, l'élève dépose sur le site sa preuve de paiement : moyen utilisé, numéro qui a payé, nom de l'expéditeur, identifiant de la transaction et capture d'écran. Un même identifiant de transaction ou une même capture ne peut être utilisé qu'une seule fois, et un seul paiement peut être en cours de vérification à la fois. Une formation déjà possédée ne peut pas être achetée une seconde fois. {{ $legal['company_name'] }} n'a jamais besoin de vos codes Mobile Money : ne les communiquez à personne.</p>

            <h2>4. Vérification du paiement et accès à la formation</h2>
            <p>Chaque paiement est vérifié par {{ $legal['company_name'] }}, {{ config('site.payments.verification_hours') }}.</p>
            <ul>
                <li>Pour une formation dont le prix est inférieur à {{ \App\Support\Money::fcfa((int) config('site.payments.verification_threshold')) }}, l'accès à la formation et à son groupe privé est ouvert dès l'envoi de la preuve, à titre provisoire, puis confirmé après vérification.</li>
                <li>À partir de {{ \App\Support\Money::fcfa((int) config('site.payments.verification_threshold')) }}, l'accès est ouvert après la vérification du paiement.</li>
                <li>Si le paiement n'est pas reçu, ou ne correspond pas à la preuve envoyée, le paiement est refusé : l'accès, même provisoire, est retiré immédiatement et l'élève en est informé par e-mail, avec le motif. En cas de fraude, le compte peut être bloqué.</li>
            </ul>
            <p>Un reçu est disponible dans l'espace élève (« Mes achats ») pour chaque paiement validé.</p>
            <p><strong class="text-white">Accès à vie et accompagnement à vie.</strong> {{ Placeholder::highlight($legal['access_duration']) }}</p>
            <p>Lorsqu'une session ponctuelle en direct ou en présentiel est organisée pour une formation, elle est annoncée sur la page de la formation. Le lien d'une session en direct est partagé dans le groupe de discussion de la formation.</p>

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
