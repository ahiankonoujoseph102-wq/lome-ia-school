<x-layouts::site title="Administration" noindex>
    <x-site.auth-card title="Administration" subtitle="Le tableau de bord complet sera construit à l'étape 10.">
        <div class="space-y-3">
            @include('site.partials.admin-payments-link', ['class' => 'card px-4 py-4 font-semibold'])
            @include('site.partials.admin-withdrawals-link', ['class' => 'card px-4 py-4 font-semibold'])
            <a href="{{ route('admin.affiliates.index') }}" class="card flex items-center gap-2 px-4 py-4 font-semibold text-slate-200 hover:bg-veil/5">
                <flux:icon.users class="size-4" /> Affiliés
                <flux:icon.chevron-right class="ml-auto size-4 text-slate-500" />
            </a>
            <a href="{{ route('admin.content.index') }}" class="card flex items-center gap-2 px-4 py-4 font-semibold text-slate-200 hover:bg-veil/5">
                <flux:icon.play-circle class="size-4" /> Contenu des formations
                <flux:icon.chevron-right class="ml-auto size-4 text-slate-500" />
            </a>
            <a href="{{ route('admin.sessions.index') }}" class="card flex items-center gap-2 px-4 py-4 font-semibold text-slate-200 hover:bg-veil/5">
                <flux:icon.calendar-days class="size-4" /> Sessions en direct et à Lomé
                <flux:icon.chevron-right class="ml-auto size-4 text-slate-500" />
            </a>
            <a href="{{ route('admin.chat.index') }}" class="card flex items-center gap-2 px-4 py-4 font-semibold text-slate-200 hover:bg-veil/5">
                <flux:icon.chat-bubble-left-right class="size-4" /> Groupes de discussion
                <flux:icon.chevron-right class="ml-auto size-4 text-slate-500" />
            </a>
            <a href="{{ route('moderation.reports.index') }}" class="card flex items-center gap-2 px-4 py-4 font-semibold text-slate-200 hover:bg-veil/5">
                <flux:icon.flag class="size-4" /> Messages signalés
                <flux:icon.chevron-right class="ml-auto size-4 text-slate-500" />
            </a>
            <a href="{{ route('admin.moderators.index') }}" class="card flex items-center gap-2 px-4 py-4 font-semibold text-slate-200 hover:bg-veil/5">
                <flux:icon.user-group class="size-4" /> Équipe de modération
                <flux:icon.chevron-right class="ml-auto size-4 text-slate-500" />
            </a>
        </div>
        <a href="{{ route('dashboard') }}" class="btn-ghost mt-5 w-full">Retour à mon espace</a>
    </x-site.auth-card>
</x-layouts::site>
