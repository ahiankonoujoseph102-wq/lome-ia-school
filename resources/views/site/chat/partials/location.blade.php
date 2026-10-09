{{--
    Lieu partagé : icône, nom, adresse et bouton « Ouvrir dans Google Maps » (aucune carte intégrée, aucune clé).
    Style stable face au mode sombre forcé : fond foncé translucide, texte clair, contour en « ring ».
--}}
@if ($location)
    <div class="chat-location" data-test="location-card">
        <div class="flex items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-rose-500/20 text-rose-200 ring-1 ring-rose-400/50" aria-hidden="true">
                <flux:icon.map-pin variant="solid" class="size-6" />
            </span>
            <span class="min-w-0 flex-1 pt-0.5">
                <span class="block text-[15px] leading-5 font-semibold break-words text-white">{{ $location->title() }}</span>
                @if (filled($location->address))
                    <span class="mt-0.5 block text-sm break-words text-slate-200">{{ $location->address }}</span>
                @endif
            </span>
        </div>
        <a href="{{ $location->maps_url }}" target="_blank" rel="noopener noreferrer nofollow" class="chat-location-link" data-test="location-open">
            <flux:icon.arrow-top-right-on-square class="size-4" /> Ouvrir dans Google Maps
        </a>
    </div>
@else
    <p class="chat-expired">Lieu indisponible</p>
@endif
