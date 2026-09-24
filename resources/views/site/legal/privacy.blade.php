@php
    use App\Support\Placeholder;

    $legal = config('site.legal');
@endphp

<x-layouts::site title="Politique de confidentialité" description="Comment Lomé IA School collecte et protège vos données personnelles.">
    @include('site.partials.page-header', ['title' => 'Politique de confidentialité'])

    <section class="mx-auto max-w-3xl px-4 pb-20 sm:px-6">
        <div class="prose-site card p-6 sm:p-10">
            <p>{{ $legal['company_name'] }} accorde une grande importance à la protection de vos données personnelles. Cette page explique quelles données nous collectons, pourquoi et quels sont vos droits.</p>

            <h2>Données collectées</h2>
            <ul>
                <li>Les informations de votre compte : nom, numéro WhatsApp, adresse e-mail et, si vous en ajoutez une, votre photo de profil.</li>
                <li>Votre choix concernant l'information sur les nouvelles formations (oui ou non).</li>
                <li>Les informations liées à vos achats : formations achetées, montants, dates.</li>
                <li>Vos preuves de paiement : moyen de paiement utilisé, numéro qui a payé, nom de l'expéditeur, identifiant de la transaction et capture d'écran du paiement.</li>
                <li>Les messages et fichiers que vous publiez dans les groupes de discussion des formations (textes, images, captures d'écran, notes vocales, vidéos, documents), ainsi que les signalements que vous faites.</li>
                <li>Vos réglages de notifications et, si vous activez les notifications sur votre téléphone, l'adresse technique d'envoi fournie par votre navigateur.</li>
                <li>Pour l'affiliation : les ventes réalisées grâce à votre lien et le numéro Mobile Money indiqué pour vos retraits.</li>
            </ul>
            <p>Les captures d'écran de paiement sont stockées dans un espace privé : elles ne sont jamais publiques et ne sont visibles que par vous et par l'équipe qui vérifie les paiements. Nous ne vous demandons jamais vos codes Mobile Money. Au Burkina Faso et au Bénin, les informations de votre paiement peuvent être communiquées à notre représentant local, uniquement pour vérifier sa réception.</p>

            <h2>Groupes de discussion</h2>
            <ul>
                <li>Dans un groupe, les autres membres voient uniquement votre nom et votre photo de profil. <strong>Votre numéro de téléphone et votre adresse e-mail ne sont jamais visibles des autres membres.</strong></li>
                <li>Les messages et fichiers d'un groupe sont stockés dans un espace privé et ne sont accessibles qu'aux membres de ce groupe et à l'équipe (formateur et modérateurs).</li>
                <li>Ils sont conservés tant que le groupe de la formation existe. Lorsqu'un message est supprimé, son contenu et son fichier sont effacés immédiatement. Si vous supprimez votre compte, vos messages et fichiers sont supprimés.</li>
                <li>L'équipe peut lire les messages signalés pour faire respecter les règles du groupe.</li>
            </ul>

            <h2>Notifications</h2>
            <ul>
                <li>Notifications sur votre téléphone : elles ne sont envoyées que si vous les activez vous-même, sur chaque appareil. Elles passent par le service de notifications de votre navigateur (par exemple Google ou Apple), qui reçoit un contenu chiffré.</li>
                <li>E-mail récapitulatif : au plus un e-mail par jour, seulement si des messages non lus vous attendent.</li>
                <li>Vous pouvez désactiver chaque type de notification, ou couper un groupe précis, à tout moment dans « Mon profil » puis « Notifications ».</li>
            </ul>

            <h2>Utilisation des données</h2>
            <ul>
                <li>Créer et gérer votre compte et vos accès aux formations ;</li>
                <li>Vérifier vos paiements, prévenir la fraude (par exemple une capture ou un identifiant de transaction déjà utilisé) et établir vos reçus ;</li>
                <li>Traiter vos commissions d'affiliation ;</li>
                <li>Vous accompagner et répondre à vos questions, notamment dans les groupes de discussion ;</li>
                <li>Vous prévenir des nouveaux messages de vos groupes, selon vos réglages de notifications ;</li>
                <li>Vous informer des nouvelles formations, uniquement si vous avez coché la case « Je souhaite être informé(e) des nouvelles formations » à l'inscription ou dans votre profil.</li>
            </ul>
            <p>Cette case n'est jamais cochée par défaut. Vous pouvez retirer votre accord à tout moment en la décochant dans votre profil. Vos données ne sont jamais vendues à des tiers.</p>

            <h2>Cookies</h2>
            <p>Le site utilise uniquement les cookies nécessaires à son fonctionnement (session de connexion et sécurité).</p>

            <h2>Durée de conservation</h2>
            <p>{{ Placeholder::highlight($legal['data_retention']) }}</p>

            <h2>Vos droits</h2>
            <p>Conformément à la loi togolaise n° 2019-014 du 29 octobre 2019 relative à la protection des données à caractère personnel, vous pouvez demander l'accès, la rectification ou la suppression de vos données en nous écrivant à <a href="mailto:{{ $legal['owner']['email'] }}">{{ $legal['owner']['email'] }}</a>. Vous pouvez aussi supprimer votre compte à tout moment depuis vos paramètres. Les informations de vos achats et vos preuves de paiement sont toutefois conservées pendant la durée indiquée ci-dessus, pour la comptabilité et la prévention de la fraude.</p>

            <p class="pt-6 text-sm text-slate-500">Dernière mise à jour : {{ Placeholder::highlight($legal['updated_at']) }}</p>
        </div>
    </section>
</x-layouts::site>
