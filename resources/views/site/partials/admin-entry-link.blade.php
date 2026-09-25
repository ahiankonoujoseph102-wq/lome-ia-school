{{-- Entrée du menu du compte vers l'administration (admin) ou la modération (modérateurs), avec le nombre de choses à traiter. --}}
@php
    $menuUser = auth()->user();
@endphp

@if ($menuUser instanceof \App\Models\User && $menuUser->isStaff())
    @php $todoCount = \App\Admin\AdminMenu::todoTotal($menuUser); @endphp
    <a href="{{ $menuUser->isAdmin() ? route('admin.index') : route('admin.sessions.index') }}" class="flex items-center gap-2 text-slate-200 hover:bg-veil/5 {{ $class ?? '' }}" data-test="admin-entry">
        <flux:icon.shield-check class="size-4" /> {{ $menuUser->isAdmin() ? 'Administration' : 'Modération et sessions' }}
        @if ($todoCount > 0)
            <span class="ml-auto rounded-full bg-gold-400/20 px-2 py-0.5 text-xs font-bold text-gold-100 ring-1 ring-gold-400/70" aria-label="{{ $todoCount }} à traiter">{{ $todoCount }}</span>
        @endif
    </a>
@endif
