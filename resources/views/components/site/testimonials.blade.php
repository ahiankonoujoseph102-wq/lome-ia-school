@php
    $testimonials = config('site.testimonials');
@endphp

<div {{ $attributes->class('grid gap-5 md:grid-cols-3') }}>
    @foreach ($testimonials as $testimonial)
        <figure class="card flex flex-col gap-5 p-6" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
            <svg viewBox="0 0 32 32" class="size-8 text-electric-400" fill="currentColor" aria-hidden="true">
                <path d="M9.3 25.3c-2.4 0-4.6-2-4.6-5.3 0-4.8 3.3-9.4 8-11.3l.9 1.6c-2.8 1.6-4.5 3.9-4.7 6.3.3-.1.7-.1 1.1-.1 2.3 0 4 1.7 4 4.2 0 2.6-2 4.6-4.7 4.6Zm13.3 0c-2.4 0-4.6-2-4.6-5.3 0-4.8 3.3-9.4 8-11.3l.9 1.6c-2.8 1.6-4.5 3.9-4.7 6.3.3-.1.7-.1 1.1-.1 2.3 0 4 1.7 4 4.2 0 2.6-2 4.6-4.7 4.6Z" />
            </svg>
            <blockquote class="flex-1 text-slate-200">
                « {{ \App\Support\Placeholder::highlight($testimonial['quote']) }} »
            </blockquote>
            <figcaption class="flex items-center gap-3">
                @if (! empty($testimonial['photo']) && file_exists(public_path('images/temoignages/'.$testimonial['photo'])))
                    <img src="{{ asset('images/temoignages/'.$testimonial['photo']) }}" alt="" class="size-11 rounded-full object-cover" loading="lazy" width="44" height="44">
                @else
                    <span class="grid size-11 place-items-center rounded-full bg-linear-to-br from-electric-500 to-electric-700 text-white">
                        <flux:icon.user class="size-5" />
                    </span>
                @endif
                <span>
                    <span class="block font-semibold text-white">{{ \App\Support\Placeholder::highlight($testimonial['name']) }}</span>
                    <span class="block text-sm text-slate-400">{{ \App\Support\Placeholder::highlight($testimonial['role']) }}</span>
                </span>
            </figcaption>
        </figure>
    @endforeach
</div>
