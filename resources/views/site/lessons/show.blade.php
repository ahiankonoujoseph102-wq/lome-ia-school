@php
    $completed = $summary?->isCompleted($lesson) ?? false;
    $backUrl = $hasAccess ? route('student.formations.show', $formation) : route('formations.show', $formation);
@endphp

<x-layouts::site :title="$lesson->title.' · '.$formation->title" :variant="$hasAccess ? 'student' : 'marketing'" noindex>
    <div class="mx-auto max-w-4xl px-4 pt-4 pb-12 sm:px-6 sm:pt-8">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> <span class="truncate">{{ $formation->title }}</span>
        </a>

        {{-- Vidéo : pleine largeur sur téléphone, plein écran autorisé --}}
        <div class="-mx-4 mt-3 overflow-hidden bg-black sm:mx-0 sm:rounded-3xl sm:ring-1 sm:ring-white/10">
            @if ($lesson->embedUrl())
                <div class="aspect-video">
                    <iframe
                        src="{{ $lesson->embedUrl() }}"
                        title="Vidéo : {{ $lesson->title }}"
                        class="size-full"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                    ></iframe>
                </div>
            @else
                <div class="grid aspect-video place-items-center bg-linear-to-br from-night-800 to-night-950 text-center">
                    <p class="px-6 text-sm text-slate-400"><flux:icon.document-text class="mx-auto mb-2 size-8 text-electric-300" /> Cette leçon n'a pas de vidéo : son contenu est ci-dessous.</p>
                </div>
            @endif
        </div>

        <div class="mt-5">
            <p class="text-xs font-semibold text-electric-300">
                {{ $lesson->module->title }}@if ($position) · Leçon {{ $position }} sur {{ $count }}@endif @if ($lesson->formatted_duration) · {{ $lesson->formatted_duration }}@endif
            </p>
            <h1 class="mt-1 text-2xl leading-tight font-bold text-balance text-white sm:text-3xl">{{ $lesson->title }}</h1>
            @if (! $lesson->is_published)
                <p class="mt-2 inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-slate-300">Brouillon : visible par l'admin uniquement</p>
            @endif
        </div>

        @if (session('status'))
            <x-site.alert class="mt-4">{{ session('status') }}</x-site.alert>
        @endif

        {{-- Progression et actions --}}
        @if ($tracked)
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('lessons.complete', [$formation, $lesson]) }}" class="flex-1 sm:flex-none">
                    @csrf
                    <input type="hidden" name="completed" value="{{ $completed ? 0 : 1 }}">
                    @if ($completed)
                        <button type="submit" class="btn w-full border border-emerald-400/40 bg-emerald-400/10 text-emerald-200 hover:bg-emerald-400/20 sm:w-auto" aria-label="Leçon terminée. Appuyer pour annuler">
                            <flux:icon.check-circle class="size-5" variant="solid" /> Terminée
                        </button>
                    @else
                        <button type="submit" class="btn-gold w-full sm:w-auto" data-test="complete-lesson">
                            <flux:icon.check-circle class="size-5" /> Marquer comme terminée
                        </button>
                    @endif
                </form>
                <div class="flex min-w-40 flex-1 items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuenow="{{ $summary->percent() }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progression dans la formation">
                        <div class="h-full rounded-full bg-linear-to-r from-electric-500 to-gold-400" style="width: {{ $summary->percent() }}%"></div>
                    </div>
                    <span class="text-sm font-semibold text-white tabular-nums">{{ $summary->percent() }} %</span>
                </div>
            </div>
        @endif

        {{-- Leçon précédente / suivante --}}
        @if ($previous || $next)
            <nav class="mt-5 grid grid-cols-2 gap-3" aria-label="Leçons">
                @if ($previous)
                    <a href="{{ route('lessons.show', [$formation, $previous]) }}" class="card card-hover flex min-w-0 flex-col p-3 text-sm">
                        <span class="inline-flex items-center gap-1 text-xs text-slate-400"><flux:icon.arrow-left class="size-3.5" /> Précédente</span>
                        <span class="mt-1 truncate font-semibold text-white">{{ $previous->title }}</span>
                    </a>
                @else
                    <span></span>
                @endif
                @if ($next)
                    <a href="{{ route('lessons.show', [$formation, $next]) }}" class="card card-hover flex min-w-0 flex-col p-3 text-right text-sm ring-1 ring-gold-400/30">
                        <span class="inline-flex items-center justify-end gap-1 text-xs text-gold-300">Suivante <flux:icon.arrow-right class="size-3.5" /></span>
                        <span class="mt-1 truncate font-semibold text-white">{{ $next->title }}</span>
                    </a>
                @endif
            </nav>
        @endif

        @if ($lesson->description)
            <section class="card mt-6 p-5">
                <h2 class="font-semibold text-white">À propos de cette leçon</h2>
                <p class="mt-2 leading-relaxed whitespace-pre-line text-slate-300">{{ $lesson->description }}</p>
            </section>
        @endif

        @if ($lesson->resources->isNotEmpty())
            <section class="card mt-6 p-5">
                <h2 class="font-semibold text-white">Fichiers à télécharger</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($lesson->resources as $resource)
                        <li>
                            <a href="{{ route('lessons.resources.download', [$formation, $lesson, $resource]) }}" class="flex items-center gap-3 rounded-2xl bg-white/[0.04] px-3 py-3 ring-1 ring-white/10 hover:bg-white/[0.08]">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-[10px] font-bold text-electric-200">{{ $resource->extension() }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-white">{{ $resource->name }}</span>
                                    <span class="text-xs text-slate-500">{{ $resource->formattedSize() }}</span>
                                </span>
                                <flux:icon.arrow-down-tray class="size-5 shrink-0 text-gold-300" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @unless ($hasAccess)
            <section class="mt-6 overflow-hidden rounded-3xl border border-gold-400/30 bg-linear-to-br from-gold-400/10 via-night-900 to-night-950 p-6 text-center">
                <p class="text-sm font-semibold text-gold-200">Aperçu gratuit</p>
                <h2 class="mt-1 text-xl font-bold text-white">Accédez à toute la formation</h2>
                <p class="mt-2 text-sm text-slate-300">Toutes les leçons, les fichiers et le groupe privé de la formation.</p>
                <a href="{{ route('checkout.show', $formation) }}" class="btn-gold mt-5 w-full sm:w-auto">Acheter · {{ $formation->formatted_price }}</a>
            </section>
        @endunless

        {{-- Sommaire --}}
        @if ($hasAccess && $curriculum->isNotEmpty())
            <details class="card group mt-6 overflow-hidden" @if (! $tracked) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-5">
                    <span class="font-semibold text-white">Sommaire de la formation</span>
                    <flux:icon.chevron-down class="size-5 text-slate-400 transition group-open:rotate-180" />
                </summary>
                <div class="border-t border-white/10 px-2 pb-3">
                    @include('site.lessons.partials.curriculum', ['current' => $lesson])
                </div>
            </details>
        @endif
    </div>
</x-layouts::site>
