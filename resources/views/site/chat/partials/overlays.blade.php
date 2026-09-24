{{-- Menus et fenêtres de la conversation (affichés par chatRoom, resources/js/chat.js). --}}

{{-- Fond sombre commun --}}
<div x-show="groupMenu || menu || dialog" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px]" @click="closeAll()"></div>

{{-- Options du groupe --}}
<div x-show="groupMenu" x-cloak x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" class="chat-sheet" role="dialog" aria-modal="true" aria-label="Options du groupe" @keydown.escape.window="closeAll()">
    <div class="chat-sheet-handle"></div>
    <p class="px-4 pb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Groupe</p>
    <a href="{{ route('chat.members', $formation) }}" class="chat-sheet-item"><flux:icon.users class="size-5 text-slate-400" /> Membres du groupe</a>
    <button type="button" class="chat-sheet-item" @click="groupMenu = false; dialog = 'rules'"><flux:icon.clipboard-document-list class="size-5 text-slate-400" /> Règles du groupe</button>
    <form method="POST" action="{{ route('chat.notifications', $formation) }}">
        @csrf
        <button type="submit" class="chat-sheet-item">
            @if ($viewer->member->notifications_muted)
                <flux:icon.bell class="size-5 text-slate-400" /> Réactiver les notifications de ce groupe
            @else
                <flux:icon.bell-slash class="size-5 text-slate-400" /> Couper les notifications de ce groupe
            @endif
        </button>
    </form>
    <a href="{{ route('student.notifications') }}" class="chat-sheet-item"><flux:icon.device-phone-mobile class="size-5 text-slate-400" /> Notifications sur mon téléphone</a>
    <a href="{{ route('student.formations.show', $formation) }}" class="chat-sheet-item" @cannot('access', $formation) hidden @endcannot><flux:icon.academic-cap class="size-5 text-slate-400" /> Aller à la formation</a>

    @if ($viewer->canModerate())
        <p class="px-4 pt-3 pb-2 text-xs font-semibold tracking-wide text-gold-300 uppercase">Modération</p>
        <form method="POST" action="{{ route('chat.announcements', $formation) }}">
            @csrf
            <button type="submit" class="chat-sheet-item" data-test="toggle-announcements">
                <flux:icon.megaphone class="size-5 text-gold-300" />
                <span x-text="announcementsOnly ? 'Désactiver le mode annonces (tout le monde écrit)' : 'Activer le mode annonces (seule l\'équipe écrit)'">{{ $group->announcements_only ? 'Désactiver le mode annonces (tout le monde écrit)' : 'Activer le mode annonces (seule l\'équipe écrit)' }}</span>
            </button>
        </form>
        <a href="{{ route('moderation.reports.index') }}" class="chat-sheet-item"><flux:icon.flag class="size-5 text-gold-300" /> Messages signalés</a>
        <a href="{{ route('chat.members', $formation) }}" class="chat-sheet-item"><flux:icon.speaker-x-mark class="size-5 text-gold-300" /> Sourdine et exclusions</a>
        @can('access-admin')
            <a href="{{ route('admin.chat.edit', $group) }}" class="chat-sheet-item"><flux:icon.pencil-square class="size-5 text-gold-300" /> Modifier les règles</a>
        @endcan
    @endif
    <button type="button" class="chat-sheet-cancel" @click="closeAll()">Fermer</button>
</div>

{{-- Actions sur un message --}}
<div x-show="menu" x-cloak x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" class="chat-sheet" role="dialog" aria-modal="true" aria-label="Actions sur le message">
    <div class="chat-sheet-handle"></div>
    <template x-if="menu">
        <div>
            <p class="line-clamp-2 px-4 pb-3 text-sm text-slate-400"><span class="font-semibold text-slate-200" x-text="menu.author"></span> : <span x-text="menu.preview"></span></p>
            <button type="button" class="chat-sheet-item" x-show="canPost" @click="reply()"><flux:icon.arrow-uturn-left class="size-5 text-slate-400" /> Répondre</button>
            <button type="button" class="chat-sheet-item" x-show="menu.text" @click="copyText()"><flux:icon.document-duplicate class="size-5 text-slate-400" /> Copier le texte</button>
            <button type="button" class="chat-sheet-item" x-show="menu.canPin" @click="togglePin()"><flux:icon.bookmark class="size-5 text-gold-300" /> <span x-text="menu.pinned ? 'Désépingler' : 'Épingler en haut du groupe'"></span></button>
            <button type="button" class="chat-sheet-item" x-show="menu.canReport" @click="dialog = 'report'; report = { reason: '', details: '' }"><flux:icon.flag class="size-5 text-slate-400" /> Signaler à l'équipe</button>
            <button type="button" class="chat-sheet-item" x-show="menu.canModerate" @click="dialog = 'mute'"><flux:icon.speaker-x-mark class="size-5 text-gold-300" /> Mettre l'auteur en sourdine</button>
            <button type="button" class="chat-sheet-item text-rose-300" x-show="menu.canModerate" @click="dialog = 'exclude'"><flux:icon.user-minus class="size-5" /> Exclure l'auteur du groupe</button>
            <button type="button" class="chat-sheet-item text-rose-300" x-show="menu.canDelete" @click="dialog = 'delete'"><flux:icon.trash class="size-5" /> Supprimer le message</button>
        </div>
    </template>
    <button type="button" class="chat-sheet-cancel" @click="closeAll()">Annuler</button>
</div>

{{-- Signalement --}}
<div x-show="dialog === 'report'" x-cloak x-transition class="chat-dialog" role="dialog" aria-modal="true" aria-labelledby="titre-signalement">
    <h2 id="titre-signalement" class="text-lg font-bold text-white">Signaler ce message</h2>
    <p class="mt-1 text-sm text-slate-400">L'équipe de modération sera prévenue. L'auteur ne saura pas qui l'a signalé.</p>
    <fieldset class="mt-4 space-y-1.5">
        <legend class="sr-only">Raison</legend>
        @foreach (\App\Enums\ReportReason::cases() as $reason)
            <label class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-200 ring-1 ring-veil/10 has-[:checked]:bg-electric-500/15 has-[:checked]:ring-electric-400/60">
                <input type="radio" name="report_reason" value="{{ $reason->value }}" x-model="report.reason" class="size-4 accent-electric-500">
                {{ $reason->label() }}
            </label>
        @endforeach
    </fieldset>
    <label for="report-details" class="field-label mt-4">Précisions (facultatif)</label>
    <textarea id="report-details" x-model="report.details" rows="2" maxlength="500" class="field-input mt-1.5 !py-2.5"></textarea>
    <div class="mt-5 flex gap-3">
        <button type="button" class="btn-ghost flex-1 !py-3" @click="closeAll()">Annuler</button>
        <button type="button" class="btn-violet flex-1 !py-3" :disabled="!report.reason || busy" @click="sendReport()">Signaler</button>
    </div>
</div>

{{-- Sourdine (équipe) --}}
<div x-show="dialog === 'mute'" x-cloak x-transition class="chat-dialog" role="dialog" aria-modal="true" aria-labelledby="titre-sourdine">
    <h2 id="titre-sourdine" class="text-lg font-bold text-white">Mettre en sourdine</h2>
    <p class="mt-1 text-sm text-slate-400"><span x-text="menu ? menu.author : ''"></span> pourra lire le groupe, mais pas écrire pendant :</p>
    <div class="mt-4 grid grid-cols-2 gap-2">
        @foreach ((array) config('chat.mute_durations') as $hours => $label)
            <button type="button" class="rounded-xl px-3 py-3 text-sm font-semibold text-slate-100 ring-1 ring-veil/15 hover:bg-veil/10" :disabled="busy" @click="mute({{ $hours }})">{{ $label }}</button>
        @endforeach
    </div>
    <button type="button" class="btn-ghost mt-4 w-full !py-3" @click="closeAll()">Annuler</button>
</div>

{{-- Confirmations --}}
<div x-show="dialog === 'delete' || dialog === 'exclude'" x-cloak x-transition class="chat-dialog" role="alertdialog" aria-modal="true">
    <h2 class="text-lg font-bold text-white" x-text="dialog === 'delete' ? 'Supprimer ce message ?' : 'Exclure ce membre ?'"></h2>
    <p class="mt-1 text-sm text-slate-400" x-text="dialog === 'delete' ? 'Le message sera remplacé par « Ce message a été supprimé » pour tous les membres.' : (menu ? menu.author : '') + ' ne pourra plus lire ni écrire dans ce groupe. Vous pourrez le réintégrer depuis la liste des membres.'"></p>
    <div class="mt-5 flex gap-3">
        <button type="button" class="btn-ghost flex-1 !py-3" @click="closeAll()">Annuler</button>
        <button type="button" class="btn flex-1 bg-rose-600 !py-3 text-white hover:bg-rose-500" :disabled="busy" @click="dialog === 'delete' ? deleteMessage() : exclude()" x-text="dialog === 'delete' ? 'Supprimer' : 'Exclure'"></button>
    </div>
</div>

{{-- Règles du groupe (affichées à l'entrée, puis à chaque modification) --}}
<div
    x-show="dialog === 'rules'"
    @if ($showRules) x-init="$nextTick(() => { if (showRules) dialog = 'rules' })" @endif
    x-cloak
    x-transition
    class="chat-dialog max-h-[85dvh] overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="titre-regles"
>
    <span class="grid size-12 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.clipboard-document-list class="size-6" /></span>
    <h2 id="titre-regles" class="mt-3 text-lg font-bold text-white">Règles du groupe</h2>
    <div class="mt-3 text-sm leading-relaxed whitespace-pre-line text-slate-300">{{ $group->rules ?: 'Restez respectueux et bienveillant envers tous les membres.' }}</div>
    <button type="button" class="btn-gold mt-5 w-full !py-3" @click="acceptRules()">J'ai compris</button>
</div>

{{-- Image en grand --}}
<div x-show="lightbox" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex flex-col bg-black/95" role="dialog" aria-modal="true" aria-label="Image en grand" @keydown.escape.window="lightbox = null">
    <div class="flex justify-end gap-1 p-2 pt-[max(env(safe-area-inset-top),0.5rem)]">
        <a :href="lightbox ? lightbox + '?telecharger=1' : '#'" class="grid size-11 place-items-center rounded-full text-white hover:bg-veil/10" aria-label="Télécharger l'image"><flux:icon.arrow-down-tray class="size-6" /></a>
        <button type="button" class="grid size-11 place-items-center rounded-full text-white hover:bg-veil/10" @click="lightbox = null" aria-label="Fermer"><flux:icon.x-mark class="size-7" /></button>
    </div>
    <div class="flex min-h-0 flex-1 items-center justify-center p-2" @click.self="lightbox = null">
        <img :src="lightbox" alt="Image en grand" class="max-h-full max-w-full object-contain">
    </div>
</div>

{{-- Accès retiré pendant la lecture --}}
<div x-show="removed" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-night-950/95 p-6 text-center">
    <div class="max-w-sm">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-rose-500/15 text-rose-300"><flux:icon.lock-closed class="size-7" /></span>
        <p class="mt-4 text-lg font-semibold text-white">Vous ne faites plus partie de ce groupe</p>
        <p class="mt-1 text-sm text-slate-400">Pour toute question, écrivez-nous sur WhatsApp.</p>
        <a href="{{ route('chat.index') }}" class="btn-ghost mt-6">Retour à mes groupes</a>
    </div>
</div>

{{-- Petit message de confirmation --}}
<div x-show="toast" x-cloak x-transition.opacity class="pointer-events-none fixed inset-x-0 top-20 z-[60] flex justify-center px-4" role="status" aria-live="polite">
    <p class="max-w-md rounded-2xl bg-night-800/95 px-4 py-3 text-center text-sm text-white shadow-2xl shadow-black/50 ring-1 ring-veil/15" x-text="toast"></p>
</div>
