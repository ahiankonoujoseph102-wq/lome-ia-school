<x-layouts::site title="Administration" noindex>
    <x-site.auth-card title="Administration" subtitle="Le tableau de bord complet sera construit à l'étape 10.">
        @include('site.partials.admin-payments-link', ['class' => 'card px-4 py-4 font-semibold'])
        <a href="{{ route('dashboard') }}" class="btn-ghost mt-5 w-full">Retour à mon espace</a>
    </x-site.auth-card>
</x-layouts::site>
