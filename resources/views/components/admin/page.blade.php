{{-- Page de l'administration : en-tête (retour, titre, sous-titre), message de réussite et contenu. --}}
@props([
    'title',
    'subtitle' => null,
    'back' => null,
    'backLabel' => 'Administration',
    'width' => 'max-w-3xl',
])

<x-layouts::site :title="$title" noindex>
    <div {{ $attributes->class(['mx-auto space-y-6 px-4 pt-6 pb-16 sm:px-6 sm:pt-8', $width]) }}>
        <div>
            <a href="{{ $back ?? route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
                <flux:icon.arrow-left class="size-4" /> {{ $backLabel }}
            </a>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-white sm:text-3xl">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-1 text-slate-400">{{ $subtitle }}</p>
                    @endif
                </div>
                {{ $actions ?? '' }}
            </div>
        </div>

        @if (session('status'))
            <x-site.alert>{{ session('status') }}</x-site.alert>
        @endif

        {{ $slot }}
    </div>
</x-layouts::site>
