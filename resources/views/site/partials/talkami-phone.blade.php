{{--
    Illustration de Talkami (accueil et page /talkami) : téléphone avec une courte conversation en anglais.
    Dessinée en HTML et CSS (pas de SVG ni d'image) : Chrome ne la recolore pas en mode sombre forcé.
    Simple illustration (aucun avis, aucun élève réel) ; décorative pour les lecteurs d'écran.
--}}
<div class="{{ $class ?? '' }} relative mx-auto w-[min(16rem,78vw)]" aria-hidden="true" data-test="talkami-phone">
    <div class="pointer-events-none absolute -inset-8 -z-10 rounded-full bg-electric-600/25 blur-3xl"></div>
    <div class="rounded-[2.2rem] bg-night-950 p-2.5 shadow-[0_0_0_1px_var(--color-night-600),0_30px_60px_-20px_rgb(0_0_0/0.8)]">
        <div class="overflow-hidden rounded-[1.7rem] bg-night-900">
            <div class="flex items-center gap-2.5 bg-night-800 px-4 py-3">
                <span class="grid size-8 place-items-center rounded-full bg-electric-600 text-sm font-bold text-white">J</span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-white">Teacher Joe</span>
                    <span class="flex items-center gap-1 text-[11px] text-emerald-300"><span class="size-1.5 rounded-full bg-emerald-400"></span> En ligne</span>
                </span>
            </div>
            <div class="space-y-2.5 px-3 py-4 text-[13px] leading-snug">
                <p class="mr-8 rounded-2xl rounded-tl-md bg-night-700 px-3 py-2 text-slate-100">Hi! What would you like to practise today?</p>
                <p class="ml-8 rounded-2xl rounded-tr-md bg-electric-700 px-3 py-2 text-white">A job interview, please.</p>
                <p class="mr-8 rounded-2xl rounded-tl-md bg-night-700 px-3 py-2 text-slate-100">Great choice. Tell me about yourself.</p>
                <div class="ml-auto flex w-fit items-center gap-2 rounded-full bg-night-800 px-3 py-2 shadow-[0_0_0_1px_var(--color-night-600)]">
                    <span class="grid size-6 place-items-center rounded-full bg-night-900 text-gold-400 shadow-[0_0_0_1.5px_var(--color-gold-500)]"><flux:icon.microphone class="size-3.5" variant="solid" /></span>
                    <span class="flex h-4 items-end gap-0.5">
                        @foreach ([40, 80, 55, 100, 65, 85, 45, 70] as $height)
                            <span class="w-0.5 rounded-full bg-gold-500" style="height: {{ $height }}%"></span>
                        @endforeach
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
