{{--
    Partager un lieu (interrupteur « Partage de localisation ») : position actuelle du téléphone ou lien Google Maps,
    nom et adresse facultatifs. Aucune carte intégrée, aucune clé : le message est une carte avec un lien.
--}}
<div x-show="dialog === 'location'" x-cloak x-transition class="chat-dialog max-h-[88dvh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="titre-lieu" data-test="location-dialog">
    <h2 id="titre-lieu" class="flex items-center gap-2 text-lg font-bold text-white">
        <flux:icon.map-pin class="size-5 text-rose-300" /> Partager un lieu
    </h2>

    <div class="mt-4 grid grid-cols-2 gap-2" role="group" aria-label="Comment partager le lieu">
        <button type="button" class="chat-choice" :class="place.mode === 'position' && 'chat-choice-on'" :aria-pressed="(place.mode === 'position').toString()" @click="place.mode = 'position'; place.error = ''" data-test="location-mode-position">
            <flux:icon.signal class="size-5 shrink-0" /> Ma position actuelle
        </button>
        <button type="button" class="chat-choice" :class="place.mode === 'link' && 'chat-choice-on'" :aria-pressed="(place.mode === 'link').toString()" @click="place.mode = 'link'; place.error = ''" data-test="location-mode-link">
            <flux:icon.link class="size-5 shrink-0" /> Coller un lien Google Maps
        </button>
    </div>

    <div x-show="place.mode === 'position'" class="mt-4">
        <button type="button" class="btn w-full bg-electric-600 !py-3 text-white hover:bg-electric-500" :disabled="place.locating" @click="locate()" data-test="location-locate">
            <span x-show="!place.locating" x-text="place.latitude === null ? 'Trouver ma position' : 'Mettre à jour ma position'">Trouver ma position</span>
            <span x-show="place.locating" x-cloak>Recherche de votre position…</span>
        </button>
        <p x-show="place.latitude !== null" x-cloak class="mt-2 flex items-center gap-1.5 text-sm text-emerald-300" data-test="location-found">
            <flux:icon.check-circle class="size-4 shrink-0" />
            <span x-text="place.accuracy ? 'Position trouvée (précision d\'environ ' + place.accuracy + ' m).' : 'Position trouvée.'"></span>
        </p>
        <p class="mt-2 text-xs text-slate-400">Votre navigateur va vous demander l'autorisation. Seul le lien du lieu est partagé dans le groupe.</p>
    </div>

    <div x-show="place.mode === 'link'" x-cloak class="mt-4">
        <label for="lieu-lien" class="field-label">Lien Google Maps</label>
        <input id="lieu-lien" type="text" inputmode="url" autocapitalize="none" autocorrect="off" spellcheck="false" x-model="place.url" maxlength="500" placeholder="https://maps.app.goo.gl/…" class="field-input mt-1.5" data-test="location-url">
        <p class="mt-1.5 text-xs text-slate-400">Dans Google Maps, touchez « Partager », puis « Copier le lien ».</p>
    </div>

    <label for="lieu-nom" class="field-label mt-4">Nom du lieu <span class="font-normal text-slate-400">(facultatif)</span></label>
    <input id="lieu-nom" type="text" x-model="place.name" maxlength="120" placeholder="Exemple : Bureau de Lomé IA School" class="field-input mt-1.5" data-test="location-name">

    <label for="lieu-adresse" class="field-label mt-4">Adresse <span class="font-normal text-slate-400">(facultatif)</span></label>
    <input id="lieu-adresse" type="text" x-model="place.address" maxlength="200" placeholder="Exemple : Adidogomé, Lomé" class="field-input mt-1.5" data-test="location-address">

    <p x-show="place.error" x-cloak x-text="place.error" class="mt-4 rounded-xl bg-rose-500/15 px-3 py-2.5 text-sm text-rose-100 ring-1 ring-rose-400/50" role="alert" data-test="location-error"></p>

    <div class="mt-5 flex gap-3">
        <button type="button" class="btn-ghost flex-1 !py-3" @click="closeAll()">Annuler</button>
        <button type="button" class="btn flex-1 bg-electric-600 !py-3 text-white hover:bg-electric-500" :disabled="busy || place.locating" @click="sendLocation()" data-test="location-send">Envoyer le lieu</button>
    </div>
</div>
