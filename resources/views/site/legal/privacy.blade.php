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
                <li>La confirmation de votre adresse e-mail : la date à laquelle elle a été confirmée et, le temps de la confirmation, une empreinte chiffrée du code et du lien qui vous ont été envoyés (jamais le code lui-même).</li>
                <li>Votre choix concernant l'information sur les nouvelles formations (oui ou non).</li>
                <li>Les informations liées à vos achats : formations achetées, montants, dates et, le cas échéant, le code promo utilisé.</li>
                <li>Vos preuves de paiement : moyen de paiement utilisé et capture d'écran du paiement (sur laquelle figurent notamment le numéro qui a payé, le nom de l'expéditeur et l'identifiant de la transaction).</li>
                <li>Les messages et fichiers que vous publiez dans les groupes de discussion des formations (textes, images, captures d'écran, notes vocales, vidéos, documents), ainsi que les signalements que vous faites.</li>
                <li>Vos réglages de notifications et, si vous activez les notifications sur votre téléphone, l'adresse technique d'envoi fournie par votre navigateur.</li>
                <li>Si vous devenez affilié : la date d'acceptation des conditions, vos coordonnées de retrait (pays, opérateur Mobile Money, numéro et nom du titulaire, ainsi que la date de leurs modifications), vos commissions et vos retraits (montants, frais d'envoi, identifiants de transaction).</li>
                <li>Si vous réservez une consultation gratuite : votre nom, votre numéro WhatsApp, votre adresse e-mail, votre profil (particulier, centre de formation ou entreprise) et, le cas échéant, le nom de votre centre ou de votre entreprise, le sujet dont vous voulez parler, la formation qui vous intéresse (facultatif) et la date du rendez-vous ; après l'appel, votre présence, les notes de {{ config('site.trainer.name') }} et la formation recommandée.</li>
                <li>Si vous acceptez de publier un témoignage : votre nom, la formation suivie, votre texte et, selon le cas, votre photo, la capture d'écran de votre message (numéro masqué) ou votre vidéo. Un témoignage n'est publié qu'avec votre accord et est retiré sur simple demande.</li>
                <li>Si vous arrivez sur le site par le lien d'un affilié : le code de ce lien et la date du clic, puis, si vous vous inscrivez ou achetez, le fait que votre inscription ou votre achat vient de ce lien.</li>
            </ul>
            <p>Les captures d'écran de paiement sont stockées dans un espace privé : elles ne sont jamais publiques et ne sont visibles que par vous et par l'équipe qui vérifie les paiements. Nous ne vous demandons jamais vos codes Mobile Money. Au Burkina Faso et au Bénin, les informations de votre paiement peuvent être communiquées à notre représentant local, uniquement pour vérifier sa réception.</p>

            <h2>Confirmation de l'adresse e-mail</h2>
            <ul>
                <li>À l'inscription, et si vous changez d'adresse, nous envoyons un code à 6 chiffres et un bouton de confirmation, valables {{ \App\Accounts\EmailConfirmation::LIFETIME_MINUTES }} minutes et utilisables une seule fois. Ils nous permettent de vérifier que l'adresse vous appartient, avant de vous y envoyer vos reçus et les informations sur vos formations.</li>
                <li>Le code n'est jamais enregistré en clair : seule une empreinte est conservée, puis supprimée dès que l'adresse est confirmée. Le nombre d'essais et de nouveaux codes est limité pour protéger votre compte.</li>
                <li>Si vous n'arrivez pas à confirmer votre adresse, notre équipe peut la confirmer pour vous après avoir vérifié avec vous qu'elle est correcte ; cette action est enregistrée.</li>
            </ul>

            <h2>Consultation gratuite</h2>
            <ul>
                <li>La réservation se fait sans compte. Si votre numéro WhatsApp ou votre adresse e-mail correspond à un compte élève, le rendez-vous est rattaché à ce compte et apparaît dans votre espace.</li>
                <li>Ces informations servent uniquement à organiser la consultation : confirmation, rappels, lien de l'appel vidéo, déplacement ou annulation, puis, éventuellement, l'envoi d'une recommandation de formation. Elles ne sont visibles que par {{ config('site.trainer.name') }}.</li>
                <li>Le lien personnel reçu par e-mail permet de voir, déplacer ou annuler le rendez-vous : ne le partagez pas.</li>
                <li><strong>Les rendez-vous et les informations du formulaire sont supprimés automatiquement {{ (int) config('consultations.retention_months', 12) }} mois après la date de la consultation.</strong> Si vous achetez ensuite une formation, les données de votre compte et de vos achats suivent les règles de conservation indiquées plus bas.</li>
            </ul>

            <h2>Affiliation</h2>
            <ul>
                <li>Un affilié voit ses statistiques (clics, inscriptions, ventes) et ses commissions, mais <strong>jamais le nom, le numéro ni l'adresse e-mail des personnes qui ont acheté</strong> grâce à son lien.</li>
                <li>Les clics sur les liens d'affiliation sont comptés de façon anonyme : nous enregistrons seulement des empreintes chiffrées (un identifiant aléatoire de votre navigateur et une empreinte de votre connexion), jamais votre adresse IP en clair, pour ne compter qu'une fois les clics répétés d'une même personne.</li>
                <li>Les coordonnées de retrait servent uniquement à payer les retraits de l'affilié. Toute modification est signalée à notre équipe, pour protéger l'affilié contre un détournement de ses commissions. Elles sont aussi comparées au numéro qui a payé un achat, pour repérer un éventuel auto-parrainage.</li>
            </ul>

            <h2>Groupes de discussion</h2>
            <ul>
                <li>Dans un groupe, les autres membres voient uniquement votre nom et votre photo de profil. <strong>Votre numéro de téléphone et votre adresse e-mail ne sont jamais visibles des autres membres.</strong></li>
                <li>Les messages et fichiers d'un groupe sont stockés dans un espace privé et ne sont accessibles qu'aux membres de ce groupe et à l'équipe (formateur et modérateurs).</li>
                <li>Les textes, images et documents sont conservés tant que le groupe de la formation existe. <strong>Les notes vocales et les vidéos sont supprimées automatiquement {{ (int) config('chat.media_retention_months', 12) }} mois après leur envoi</strong> : le message reste dans la conversation avec la mention « Fichier expiré ». Lorsqu'un message est supprimé, son contenu et son fichier sont effacés immédiatement. Si vous supprimez votre compte, vos messages et fichiers sont supprimés.</li>
                <li>L'équipe peut lire les messages signalés pour faire respecter les règles du groupe.</li>
            </ul>

            <h2>Notifications</h2>
            <ul>
                <li>Notifications sur votre téléphone : elles ne sont envoyées que si vous les activez vous-même, sur chaque appareil. Elles passent par le service de notifications de votre navigateur (par exemple Google ou Apple), qui reçoit un contenu chiffré.</li>
                <li>E-mail récapitulatif : au plus un e-mail par jour, seulement si des messages non lus vous attendent.</li>
                <li>Vous pouvez désactiver chaque type de notification, ou couper un groupe précis, à tout moment dans « Mon profil » puis « Notifications ».</li>
            </ul>

            <h2>Assistante Ama</h2>
            @if (\App\Support\VoiceAssistant::usesGemini())
                <div data-test="privacy-voice-assistant">
                    <p>Le bouton « Parler avec notre assistante » vous permet de discuter avec Ama, notre assistante, par écrit ou à la voix. <strong>Ama est une intelligence artificielle.</strong> Ses réponses sont produites par Gemini, un service d'intelligence artificielle de Google : vos messages lui sont transmis pour obtenir la réponse.</p>
                    <p data-test="privacy-gemini">Nous utilisons l'offre gratuite de Gemini. Sur cette offre, Google peut utiliser les échanges pour améliorer ses services. C'est pourquoi aucune donnée personnelle n'est transmise : les numéros de téléphone, adresses e-mail et codes que vous écririez sont retirés de vos messages avant l'envoi. N'écrivez jamais de mot de passe ni de code secret dans la conversation.</p>
                    <p>Pour vous guider, l'assistante reçoit des informations sur la page que vous consultez (par exemple la formation, le montant à payer ou l'étape du paiement) et sur les éventuelles erreurs affichées à l'écran, sans aucune donnée personnelle : ni nom, ni adresse e-mail, ni numéro de téléphone, ni mot de passe, ni code, ni capture de paiement.</p>
                    <p>Si vous utilisez le micro, votre voix est transformée en texte par votre navigateur (par exemple par Google sur Chrome ou par Apple sur Safari) : seul le texte nous parvient. La lecture des réponses à voix haute est faite par votre navigateur. Le micro ne s'ouvre jamais sans que vous touchiez son bouton.</p>
                    <p>Les conversations (messages nettoyés et page consultée) sont conservées 90 jours sur notre site, puis effacées automatiquement. Elles ne sont liées ni à votre nom ni à votre compte : un identifiant aléatoire, enregistré dans un cookie de votre navigateur, sert seulement à limiter le nombre de messages par jour. La conversation en cours est aussi gardée dans votre navigateur tant que l'onglet reste ouvert.</p>
                </div>
            @else
                <p data-test="privacy-voice-assistant">Le bouton « Parler avec notre assistante » vous permet de discuter avec Ama, notre assistante vocale. <strong>Ama est une intelligence artificielle.</strong> Vos conversations avec elle sont traitées par le service ElevenLabs. L'audio et la transcription de chaque conversation sont conservés 90 jours. Pour fonctionner, le widget d'ElevenLabs enregistre dans votre navigateur un identifiant technique de visiteur. Pour vous guider, l'assistante reçoit des informations sur la page que vous consultez (par exemple la formation, le montant à payer ou l'étape du paiement) et sur les éventuelles erreurs affichées à l'écran, sans aucune donnée personnelle : ni nom, ni adresse e-mail, ni numéro de téléphone, ni mot de passe, ni code, ni capture de paiement.</p>
            @endif

            <h2>Utilisation des données</h2>
            <ul>
                <li>Créer et gérer votre compte et vos accès aux formations ;</li>
                <li>Vérifier que votre adresse e-mail vous appartient (code de confirmation) avant tout paiement ou retrait ;</li>
                <li>Vérifier vos paiements, prévenir la fraude (par exemple une capture ou un paiement déjà utilisé) et établir vos reçus ;</li>
                <li>Compter les clics sur les liens d'affiliation, attribuer les achats au bon affilié, calculer et payer les commissions, et prévenir la fraude à l'affiliation ;</li>
                <li>Vous accompagner et répondre à vos questions, notamment dans les groupes de discussion ;</li>
                <li>Organiser votre consultation gratuite (confirmation, rappels, lien de l'appel vidéo) et, si vous le souhaitez, vous recommander une formation ;</li>
                <li>Vous prévenir des nouveaux messages de vos groupes, selon vos réglages de notifications ;</li>
                <li>Vous informer des nouvelles formations, uniquement si vous avez coché la case « Je souhaite être informé(e) des nouvelles formations » à l'inscription ou dans votre profil.</li>
            </ul>
            <p>Cette case n'est jamais cochée par défaut. Vous pouvez retirer votre accord à tout moment en la décochant dans votre profil. Vos données ne sont jamais vendues à des tiers.</p>

            <h2>Cookies</h2>
            <p>Le site utilise uniquement les cookies nécessaires à son fonctionnement (session de connexion et sécurité), ainsi que deux cookies liés au programme d'affiliation, déposés seulement lorsque vous cliquez sur le lien d'un affilié :</p>
            <ul>
                <li><strong>lis_ref</strong> : le code du dernier lien d'affiliation cliqué et la date du clic, conservé {{ config('affiliate.cookie_days') }} jours, pour attribuer votre éventuel achat à l'affilié qui vous a recommandé le site ;</li>
                <li><strong>lis_visitor</strong> : un identifiant aléatoire, conservé 12 mois, pour ne compter qu'une fois vos clics sur le lien d'un même affilié.</li>
            </ul>
            <p>Aucun cookie publicitaire ni de mesure d'audience tiers n'est utilisé. Vous pouvez supprimer ces cookies à tout moment depuis les réglages de votre navigateur.</p>

            <h2>Hébergement et lieu de stockage</h2>
            <p>Le site et ses données (compte, achats, preuves de paiement, messages et fichiers des groupes) sont hébergés par {{ $legal['host'] }}@if (filled($legal['data_location'] ?? null)), dans la région suivante : {{ $legal['data_location'] }}@endif. Les fichiers privés restent dans un espace privé, accessible uniquement par le site.</p>

            <h2>Durée de conservation</h2>
            <p>{{ Placeholder::highlight($legal['data_retention']) }}</p>

            <h2>Vos droits</h2>
            <p>Conformément à la loi togolaise n° 2019-014 du 29 octobre 2019 relative à la protection des données à caractère personnel, vous pouvez demander l'accès, la rectification ou la suppression de vos données en nous écrivant à <a href="mailto:{{ $legal['owner']['email'] }}">{{ $legal['owner']['email'] }}</a>. Vous pouvez aussi supprimer votre compte à tout moment depuis vos paramètres. Les informations de vos achats et vos preuves de paiement sont toutefois conservées pendant la durée indiquée ci-dessus, pour la comptabilité et la prévention de la fraude.</p>

            <p class="pt-6 text-sm text-slate-500">Dernière mise à jour : {{ Placeholder::highlight($legal['updated_at']) }}</p>
        </div>
    </section>
</x-layouts::site>
