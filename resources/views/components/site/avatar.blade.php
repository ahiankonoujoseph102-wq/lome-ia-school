@props(['user'])

{{-- Photo de profil de l'élève, ou ses initiales sur un dégradé violet. --}}
@if ($user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" {{ $attributes->class('shrink-0 rounded-full object-cover ring-2 ring-electric-400/40') }} width="96" height="96" loading="lazy">
@else
    <span {{ $attributes->class('grid shrink-0 place-items-center rounded-full bg-linear-to-br from-electric-500 to-electric-700 font-bold text-white ring-2 ring-electric-400/40') }} aria-hidden="true">{{ $user->initials() }}</span>
@endif
