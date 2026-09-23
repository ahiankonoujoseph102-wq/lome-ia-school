<x-layouts::site title="Administration" noindex>
    <x-site.auth-card title="Administration" subtitle="Le tableau de bord d'administration sera construit à l'étape 10.">
        <p class="text-sm text-slate-300">Vous êtes connecté avec un compte administrateur. Cette zone est déjà protégée : seuls les comptes admin peuvent y accéder.</p>
        <a href="{{ route('dashboard') }}" class="btn-ghost mt-5 w-full">Retour à mon espace</a>
    </x-site.auth-card>
</x-layouts::site>
