{{--
    Logo du site : celui envoyé depuis l'admin (Réglages du site > Logo), sinon public/images/logo.svg
    (ou logo.png), sinon ce logo provisoire.
--}}
@php
    $uploadedLogo = config('site.brand.logo');
    $logoUrl = is_string($uploadedLogo) && $uploadedLogo !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($uploadedLogo)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($uploadedLogo)
        : collect(['images/logo.svg', 'images/logo.png'])->filter(fn ($file) => file_exists(public_path($file)))->map(fn ($file) => asset($file))->first();
@endphp

<a href="{{ route('home') }}" {{ $attributes->class('group inline-flex shrink-0 items-center gap-2.5') }} aria-label="{{ config('app.name') }} : accueil">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="h-9 w-auto" width="160" height="36">
    @else
        <span class="relative grid size-9 place-items-center rounded-xl bg-linear-to-br from-electric-500 to-electric-700 shadow-lg shadow-electric-700/40 ring-1 ring-veil/20 transition group-hover:scale-105">
            <svg viewBox="0 0 24 24" class="size-5 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 4v16h8" />
                <path d="M16 4l1.2 2.8L20 8l-2.8 1.2L16 12l-1.2-2.8L12 8l2.8-1.2z" fill="currentColor" stroke="none" />
            </svg>
            <span class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full bg-gold-400 ring-2 ring-night-950"></span>
        </span>
        <span class="text-lg leading-none font-bold tracking-tight text-white">
            Lomé <span class="text-electric-300">IA</span> School
        </span>
    @endif
</a>
