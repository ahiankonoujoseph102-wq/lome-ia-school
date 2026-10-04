{{--
    Zone d'écriture : texte, photo ou capture, vidéo courte, fichier, note vocale, réponse à un message.
    Sans JavaScript, le formulaire envoie quand même un message texte.
--}}
<form
    method="POST"
    action="{{ route('chat.messages.store', $formation) }}"
    x-show="canPost"
    @unless ($viewer->canPost()) style="display: none" @endunless
    @submit.prevent="sendText()"
    class="space-y-2"
    data-test="composer"
>
    @csrf
    <input type="hidden" name="type" value="text">

    @error('body')
        <x-site.alert type="error">{{ $message }}</x-site.alert>
    @enderror

    {{-- Réponse à un message (préparée par le geste vers la droite ou par « Répondre » dans le menu) --}}
    <div x-show="replyTo" x-cloak class="chat-reply-draft" data-test="reply-draft">
        <span class="h-9 w-1 shrink-0 rounded-full bg-electric-400"></span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-xs font-semibold text-electric-200" x-text="replyTo ? 'Réponse à ' + replyTo.author : ''"></span>
            <span class="block truncate text-sm text-slate-300" x-text="replyTo ? replyTo.preview : ''"></span>
        </span>
        <template x-if="replyTo && replyTo.thumb">
            <img :src="replyTo.thumb" alt="" class="size-10 shrink-0 rounded-lg bg-black/30 object-cover">
        </template>
        <button type="button" @click="cancelReply()" class="grid size-9 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-veil/10 hover:text-white" aria-label="Annuler la réponse">
            <flux:icon.x-mark class="size-5" />
        </button>
    </div>

    {{-- Fichier choisi : aperçu, légende, envoi --}}
    <div x-show="pending" x-cloak class="rounded-2xl bg-veil/[0.06] p-3 ring-1 ring-veil/10">
        <div class="flex items-center gap-3">
            <template x-if="pending && pending.preview && pending.type === 'image'">
                <img :src="pending.preview" alt="Aperçu" class="size-16 shrink-0 rounded-xl object-cover">
            </template>
            <template x-if="pending && pending.preview && pending.type === 'video'">
                <video :src="pending.preview" muted playsinline class="size-16 shrink-0 rounded-xl bg-black object-cover"></video>
            </template>
            <template x-if="pending && pending.type === 'file'">
                <span class="chat-file-icon size-16 shrink-0 text-xs" x-text="pending.extension"></span>
            </template>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-white" x-text="pending ? pending.name : ''"></span>
                <span class="block text-xs text-slate-400" x-text="pending ? pending.label : ''"></span>
            </span>
            <button type="button" @click="clearPending()" class="grid size-9 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-veil/10 hover:text-white" aria-label="Retirer le fichier">
                <flux:icon.x-mark class="size-5" />
            </button>
        </div>
        <div x-show="uploading" class="mt-3">
            <div class="h-1.5 overflow-hidden rounded-full bg-veil/10">
                <div class="h-full rounded-full bg-linear-to-r from-electric-500 to-gold-400 transition-[width]" :style="`width: ${uploadProgress}%`"></div>
            </div>
            <p class="mt-1 text-xs text-slate-400">Envoi en cours… <span x-text="uploadProgress + ' %'"></span></p>
        </div>
    </div>

    {{-- Enregistrement d'une note vocale --}}
    <div x-show="recording" x-cloak class="flex items-center gap-2 rounded-full bg-veil/[0.06] py-1.5 pr-1.5 pl-2 ring-1 ring-veil/10" role="status">
        <button type="button" @click="cancelRecording()" class="grid size-10 shrink-0 place-items-center rounded-full text-rose-300 hover:bg-rose-500/15" aria-label="Annuler la note vocale">
            <flux:icon.trash class="size-5" />
        </button>
        <span class="size-2.5 shrink-0 animate-pulse rounded-full bg-rose-500"></span>
        <span class="flex-1 text-sm text-slate-200 tabular-nums">
            <span x-text="formatSeconds(recordSeconds)">0:00</span>
            <span class="text-slate-500">/ {{ intdiv((int) config('chat.attachments.voice.max_seconds'), 60) }}:00</span>
        </span>
        <button type="button" @click="stopRecording(true)" class="grid size-11 shrink-0 place-items-center rounded-full bg-electric-600 text-white shadow-lg shadow-electric-900/40" aria-label="Envoyer la note vocale">
            <flux:icon.paper-airplane variant="solid" class="size-5" />
        </button>
    </div>

    {{-- Ligne d'écriture --}}
    <div x-show="!recording" class="flex items-end gap-1.5">
        <div class="relative" @click.outside="attachMenu = false">
            <button type="button" @click="attachMenu = !attachMenu" class="grid size-11 place-items-center rounded-full text-slate-300 hover:bg-veil/10 hover:text-white" aria-label="Joindre une photo, une vidéo ou un fichier" :aria-expanded="attachMenu.toString()">
                <flux:icon.paper-clip class="size-6" />
            </button>
            <div x-show="attachMenu" x-cloak x-transition.origin.bottom.left class="absolute bottom-14 left-0 z-30 w-64 overflow-hidden rounded-2xl border border-veil/10 bg-night-800/98 p-1.5 shadow-2xl shadow-black/60 backdrop-blur-xl">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-3 text-sm text-slate-100 hover:bg-veil/5">
                    <span class="grid size-9 place-items-center rounded-full bg-sky-500/20 text-sky-300"><flux:icon.photo class="size-5" /></span>
                    <span>Photo ou capture d'écran</span>
                    <input type="file" accept="image/*" class="sr-only" @change="pick($event, 'image')">
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-3 text-sm text-slate-100 hover:bg-veil/5">
                    <span class="grid size-9 place-items-center rounded-full bg-pink-500/20 text-pink-300"><flux:icon.video-camera class="size-5" /></span>
                    <span>Vidéo courte <span class="text-xs text-slate-400">({{ intdiv((int) config('chat.attachments.video.max_kb'), 1024) }} Mo max.)</span></span>
                    <input type="file" accept="video/mp4,video/quicktime,video/webm,video/3gpp,video/*" class="sr-only" @change="pick($event, 'video')">
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-3 text-sm text-slate-100 hover:bg-veil/5">
                    <span class="grid size-9 place-items-center rounded-full bg-amber-500/20 text-amber-300"><flux:icon.document-text class="size-5" /></span>
                    <span>Fichier <span class="block text-xs text-slate-400">PDF, Word, Excel, PowerPoint, ZIP, TXT</span></span>
                    <input type="file" accept="{{ $fileAccept }}" class="sr-only" @change="pick($event, 'file')">
                </label>
            </div>
        </div>

        <label for="chat-body" class="sr-only">Votre message</label>
        <textarea
            id="chat-body"
            name="body"
            x-ref="input"
            x-model="text"
            rows="1"
            maxlength="{{ config('chat.max_text_length') }}"
            placeholder="Écrire un message"
            @input="autosize()"
            @keydown.enter="onEnter($event)"
            @keydown.escape="cancelReply()"
            class="chat-input"
        ></textarea>

        <button
            type="submit"
            x-show="text.trim() !== '' || pending"
            class="grid size-11 shrink-0 place-items-center rounded-full bg-electric-600 text-white shadow-lg shadow-electric-900/40 transition active:scale-95 disabled:opacity-50"
            :disabled="sending"
            aria-label="Envoyer"
        >
            <flux:icon.paper-airplane variant="solid" class="size-5" />
        </button>
        <button
            type="button"
            x-show="text.trim() === '' && !pending"
            x-cloak
            @click="startRecording()"
            class="grid size-11 shrink-0 place-items-center rounded-full bg-electric-600 text-white shadow-lg shadow-electric-900/40 transition active:scale-95"
            aria-label="Enregistrer une note vocale"
        >
            <flux:icon.microphone class="size-5" />
        </button>
    </div>
</form>
