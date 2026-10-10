{{--
    « Pourquoi nous faire confiance » (page /accompagnement) : faits lus dans les réglages du site
    (App\Packs\PackTrust::facts) ; une information absente n'a pas de ligne. Aucun chiffre ajouté, aucune note,
    aucun logo, aucune promesse de résultat. Lien vers la page de Joseph Ahiankonou.
    Style stable face au mode sombre forcé : fonds foncés, textes clairs, filets dessinés en ombre.

    @var list<array{key: string, icon: string, title: string, lines: list<string>, link: array{url: string, label: string, external: bool}|null}> $facts
--}}
<section class="mx-auto max-w-3xl px-4 pt-14 sm:px-6 sm:pt-20" data-test="packs-trust">
    <h2 class="text-center text-2xl font-bold tracking-tight text-balance text-white sm:text-3xl">Pourquoi nous faire confiance</h2>

    <ul class="mt-6 grid gap-3 sm:grid-cols-2">
        @foreach ($facts as $fact)
            <li class="flex min-w-0 gap-3 rounded-2xl bg-night-900/70 p-4 shadow-[inset_0_0_0_1px_var(--color-night-700)]" data-test="packs-trust-{{ $fact['key'] }}">
                <span @class([
                    'grid size-9 shrink-0 place-items-center rounded-full',
                    'bg-[#25D366] text-white' => $fact['icon'] === 'whatsapp',
                    'bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30' => $fact['icon'] !== 'whatsapp',
                ]) aria-hidden="true">
                    @if ($fact['icon'] === 'whatsapp')
                        <x-site.whatsapp-icon class="size-5" />
                    @else
                        <flux:icon :icon="$fact['icon']" class="size-5" />
                    @endif
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white">{{ $fact['title'] }}</p>
                    @foreach ($fact['lines'] as $line)
                        <p class="mt-0.5 text-sm text-pretty break-words text-slate-300">{{ $line }}</p>
                    @endforeach
                    @if ($fact['link'])
                        <a
                            href="{{ $fact['link']['url'] }}"
                            @if ($fact['link']['external']) target="_blank" rel="noopener" @endif
                            class="mt-1.5 inline-flex items-center gap-1 text-sm font-semibold text-electric-200 hover:text-white"
                        >{{ $fact['link']['label'] }} <flux:icon.arrow-right class="size-4" /></a>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    <p class="mt-5 text-center">
        <a href="{{ route('founder') }}" class="btn-ghost w-full sm:w-auto" data-test="packs-trust-founder">
            Découvrir le parcours de {{ \App\Support\Founder::text('nickname') ?: \App\Support\Founder::text('name') }} <flux:icon.arrow-right class="size-4" />
        </a>
    </p>
</section>
