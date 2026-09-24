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

            <h2>5. Usage personnel</h2>
            <p>L'accès à une formation est strictement personnel. Le partage des identifiants, l'enregistrement, la copie ou la revente des contenus sont interdits et peuvent entraîner la suspension de l'accès sans remboursement.</p>

            <h2>6. Sessions ponctuelles en direct et en présentiel</h2>
            <p>Les formations sont enregistrées. De temps en temps, {{ $legal['company_name'] }} organise pour une formation une session ponctuelle, en direct sur Google Meet ou en présentiel à Lomé. Ces sessions ne sont pas garanties : elles sont annoncées sur la page de la formation, dans l'espace élève et dans le groupe de discussion de la formation. Elles sont réservées aux élèves qui ont accès à la formation (accès provisoire compris) ; si l'accès est retiré, la participation l'est aussi.</p>
            <ul>
                <li><strong class="text-white">En direct</strong> : sans inscription. Le lien Google Meet est communiqué uniquement aux élèves de la formation et ne doit pas être partagé.</li>
                <li><strong class="text-white">En présentiel</strong> : inscription obligatoire dans l'espace élève, dans la limite des places disponibles. Si la session est complète, l'élève peut s'inscrire sur la liste d'attente : lorsqu'une place se libère, la première personne en attente est inscrite automatiquement et prévenue.</li>
                <li><strong class="text-white">Annulation par l'élève</strong> : possible depuis l'espace élève jusqu'à {{ config('live_sessions.cancel_deadline_hours') }} heures avant le début de la session. Passé ce délai, l'inscription ne peut plus être annulée.</li>
                <li><strong class="text-white">Supplément</strong> : une session en présentiel peut être incluse dans la formation ou demander un supplément, indiqué en FCFA avant l'inscription. Le supplément se paie par Mobile Money avec preuve de paiement, selon les mêmes règles que les formations (article 4) : la place est réservée pendant la vérification et libérée si le paiement est refusé ou si la preuve n'est pas envoyée dans les {{ config('live_sessions.payment_hold_hours') }} heures. Un reçu est disponible dans « Mes achats ».</li>
                <li><strong class="text-white">Remboursement du supplément</strong> : le supplément payé est remboursé par Mobile Money si l'élève annule lui-même son inscription dans le délai autorisé (jusqu'à {{ config('live_sessions.cancel_deadline_hours') }} heures avant le début de la session), ou si {{ $legal['company_name'] }} annule la session. Il n'est pas remboursé si l'élève ne se présente pas sans avoir annulé son inscription.</li>
                <li><strong class="text-white">Annulation ou modification par {{ $legal['company_name'] }}</strong> : en cas de changement de date, d'heure, de lieu ou de lien, ou d'annulation, les élèves concernés sont prévenus par e-mail et notification. Si {{ $legal['company_name'] }} annule une session, le supplément éventuellement payé est remboursé par Mobile Money.</li>
            </ul>
            <p>Les heures sont indiquées à l'heure de Lomé (GMT), qui est aussi celle du Burkina Faso ; au Bénin (GMT+1), il faut ajouter une heure.</p>

            <h2>7. Groupes de discussion</h2>
            <p>Chaque formation dispose d'un groupe de discussion privé, réservé aux élèves qui y ont accès et à l'équipe de {{ $legal['company_name'] }} (formateur et modérateurs). L'élève y est ajouté automatiquement dès l'ouverture de son accès et en est retiré si l'accès est retiré (paiement refusé, remboursement) ou si son compte est bloqué.</p>
            <p>En participant à un groupe, l'élève s'engage à :</p>
            <ul>
                <li>rester respectueux et bienveillant envers tous les membres (aucune insulte, moquerie ni harcèlement) ;</li>
                <li>publier des messages en lien avec la formation ;</li>
                <li>ne pas faire de publicité, de vente, de promotion de liens d'affiliation ni envoyer de messages répétés ;</li>
                <li>ne publier aucun contenu illégal, choquant, trompeur ou portant atteinte aux droits d'autrui ;</li>
                <li>ne pas partager le contenu de la formation ou les fichiers du groupe en dehors de celui-ci ;</li>
                <li>ne pas demander ni publier de numéros de téléphone ou d'autres données personnelles.</li>
            </ul>
            <p>Les règles de chaque groupe sont affichées à l'entrée du groupe. Tout membre peut signaler un message à l'équipe. En cas de non-respect de ces règles, l'équipe peut, sans préavis, supprimer un message, mettre un membre en sourdine (lecture seule pendant une durée déterminée) ou l'exclure du groupe. L'exclusion d'un groupe ne donne droit à aucun remboursement ; elle ne retire pas l'accès aux leçons de la formation, sauf en cas de manquement prévu à l'article 5.</p>

            <h2>8. Remboursement</h2>
            <p>{{ Placeholder::highlight($legal['refund_policy']) }}</p>

            <h2>9. Programme d'affiliation</h2>
            <p>Les élèves peuvent recommander les formations grâce à un lien personnel et percevoir une commission de {{ config('site.affiliate.rate') }} % du prix payé pour chaque vente réalisée grâce à ce lien. Les demandes de retrait par Mobile Money sont vérifiées puis validées par {{ $legal['company_name'] }}. Toute fraude (auto-parrainage, fausses commandes…) entraîne l'annulation des commissions concernées.</p>

            <h2>10. Contact</h2>
            <p>Pour toute question ou demande de remboursement : <a href="mailto:{{ $legal['owner']['email'] }}">{{ $legal['owner']['email'] }}</a> ou {{ $legal['owner']['phone'] }} (appel ou WhatsApp), {{ mb_lcfirst($contact['hours']) }}.</p>

            <p class="pt-6 text-sm text-slate-500">Dernière mise à jour : {{ Placeholder::highlight($legal['updated_at']) }}</p>
        </div>
    </section>
</x-layouts::site>
