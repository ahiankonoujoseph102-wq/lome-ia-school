{{--
    Texte saisi dans l'admin, affiché avec ses retours à la ligne et ses lignes à tiret en liste à puces
    (App\Support\RichText). Chaque ligne est échappée : aucun code HTML saisi n'est interprété.
--}}
@props(['text'])

<div {{ $attributes->class('space-y-3') }}>
    @foreach (\App\Support\RichText::blocks($text) as $block)
        @if ($block['type'] === 'list')
            <ul class="space-y-1.5">
                @foreach ($block['items'] as $item)
                    <li class="flex gap-2"><span class="mt-[0.55em] size-1.5 shrink-0 rounded-full bg-gold-500" aria-hidden="true"></span><span class="min-w-0">{{ $item }}</span></li>
                @endforeach
            </ul>
        @else
            <p>
                @foreach ($block['lines'] as $line)
                    {{ $line }}@unless ($loop->last)<br>@endunless
                @endforeach
            </p>
        @endif
    @endforeach
</div>
