@php
    use App\Support\Placeholder;

    $legal = config('site.legal');
    $owner = $legal['owner'];
@endphp

<x-layouts::site title="Mentions légales" description="Mentions légales du site Lomé IA School.">
    @include('site.partials.page-header', ['title' => 'Mentions légales'])

    <section class="mx-auto max-w-3xl px-4 pb-20 sm:px-6">
        <div class="prose-site card p-6 sm:p-10">
            <h2>Responsable du site</h2>
            <p>Le site {{ $legal['company_name'] }} est édité par :</p>
            <ul>
                <li>{{ $owner['name'] }}</li>
                <li>Adresse : {{ $owner['address'] }}</li>
                <li>Téléphone : <a href="{{ \App\Support\WhatsApp::tel($owner['phone']) }}">{{ $owner['phone'] }}</a></li>
                <li>E-mail : <a href="mailto:{{ $owner['email'] }}">{{ $owner['email'] }}</a></li>
                <li>Directeur de la publication : {{ $owner['name'] }}</li>
            </ul>

            <h2>Hébergement</h2>
            <p>{{ $legal['host'] }}</p>

            <h2>Propriété intellectuelle</h2>
            <p>L'ensemble des contenus du site et des formations (textes, vidéos, images, supports) est la propriété de {{ $legal['company_name'] }} ou de ses partenaires. Toute reproduction, diffusion ou revente, totale ou partielle, sans autorisation écrite préalable est interdite. L'accès à une formation est personnel et ne peut pas être partagé.</p>

            <h2>Responsabilité</h2>
            <p>{{ $legal['company_name'] }} s'efforce de fournir des informations exactes et à jour. Les résultats obtenus après une formation dépendent de l'implication et du travail de chaque élève et ne peuvent pas être garantis.</p>

            <h2>Données personnelles</h2>
            <p>Le traitement de vos données est décrit dans notre <a href="{{ route('legal.privacy') }}">politique de confidentialité</a>.</p>

            <p class="pt-6 text-sm text-slate-500">Dernière mise à jour : {{ Placeholder::highlight($legal['updated_at']) }}</p>
        </div>
    </section>
</x-layouts::site>
