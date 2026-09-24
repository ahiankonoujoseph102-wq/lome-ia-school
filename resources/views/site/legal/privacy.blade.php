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
                <li>Les messages que vous publiez dans les groupes de discussion des formations.</li>
                <li>Pour l'affiliation : les ventes réalisées grâce à votre lien et le numéro Mobile Money indiqué pour vos retraits.</li>
            </ul>
            <p>Les paiements sont traités par FedaPay. Nous ne conservons jamais vos codes ni vos identifiants Mobile Money.</p>

            <h2>Utilisation des données</h2>
            <ul>
                <li>Créer et gérer votre compte et vos accès aux formations ;</li>
                <li>Traiter vos paiements et vos commissions d'affiliation ;</li>
                <li>Vous accompagner et répondre à vos questions ;</li>
                <li>Vous informer des nouvelles formations, uniquement si vous avez coché la case « Je souhaite être informé(e) des nouvelles formations » à l'inscription ou dans votre profil.</li>
            </ul>
            <p>Cette case n'est jamais cochée par défaut. Vous pouvez retirer votre accord à tout moment en la décochant dans votre profil. Vos données ne sont jamais vendues à des tiers.</p>

            <h2>Cookies</h2>
            <p>Le site utilise uniquement les cookies nécessaires à son fonctionnement (session de connexion et sécurité).</p>

            <h2>Durée de conservation</h2>
            <p>{{ Placeholder::highlight($legal['data_retention']) }}</p>

            <h2>Vos droits</h2>
            <p>Conformément à la loi togolaise n° 2019-014 du 29 octobre 2019 relative à la protection des données à caractère personnel, vous pouvez demander l'accès, la rectification ou la suppression de vos données en nous écrivant à <a href="mailto:{{ $legal['owner']['email'] }}">{{ $legal['owner']['email'] }}</a>. Vous pouvez aussi supprimer votre compte à tout moment depuis vos paramètres.</p>

            <p class="pt-6 text-sm text-slate-500">Dernière mise à jour : {{ Placeholder::highlight($legal['updated_at']) }}</p>
        </div>
    </section>
</x-layouts::site>
