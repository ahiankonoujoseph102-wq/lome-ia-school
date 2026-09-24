{{-- Photo du formateur : public/images/formateur.jpg (carrée). En son absence, ses initiales s'affichent. --}}
@php
    $photo = config('site.trainer.photo');
    $hasPhoto = $photo && file_exists(public_path($photo));
    $initials = collect(explode(' ', config('site.trainer.name')))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
@endphp

@if ($hasPhoto)
    <img src="{{ asset($photo) }}" alt="{{ config('site.trainer.photo_alt', config('site.trainer.name')) }}" width="800" height="800" class="{{ $size ?? 'size-20' }} shrink-0 rounded-2xl object-cover ring-2 ring-electric-400/40" loading="{{ $loading ?? 'lazy' }}" decoding="async">
@else
    <span class="{{ $size ?? 'size-20' }} grid shrink-0 place-items-center rounded-2xl bg-linear-to-br from-electric-500 via-electric-700 to-night-700 text-2xl font-bold text-white ring-2 ring-electric-400/40" aria-hidden="true">{{ $initials }}</span>
@endif
