@php
    use App\Support\Placeholder;

    $legal = config('site.legal');
    $contact = config('site.contact');
@endphp

<x-layouts::site title="Mentions légales" description="Mentions légales du site Lomé IA School.">
    @include('site.partials.page-header', ['title' => 'Mentions légales'])

    <section class="mx-auto max-w-3xl px-4 pb-20 sm:px-6">
        <div class="prose-site card p-6 sm:p-10">
            <h2>Éditeur du site</h2>
            <ul>
                <li>Nom : {{ $legal['company_name'] }}</li>
                <li>Forme juridique : {{ Placeholder::highlight($legal['legal_form']) }}</li>
                <li>Immatriculation : {{ Placeholder::highlight($legal['registration']) }}</li>
                <li>Identifiant fiscal : {{ Placeholder::highlight($legal['tax_id']) }}</li>
                <li>Adresse : {{ $contact['address'] }}</li>
                <li>Téléphone : {{ implode(' · ', $contact['phones']) }}</li>
                <li>E-mail : <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
                <li>Directeur de la publication : {{ $legal['publisher'] }}</li>
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
