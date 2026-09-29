@php
    use App\Consultations\ConsultationTime;
    use App\Enums\ConsultationAttendance;
    use App\LiveSessions\SessionTime;
    use App\Support\PhoneNumber;

    $whatsapp = 'https://wa.me/'.ltrim($consultation->phone, '+').'?text='.rawurlencode('Bonjour '.$consultation->name.', c\'est Joseph de Lomé IA School, au sujet de votre consultation gratuite du '.ConsultationTime::short($consultation).'.');
    $cancelErrors = $errors->getBag('cancel'.$consultation->id);
@endphp

<article class="card p-5" id="consultation-{{ $consultation->id }}" data-test="admin-consultation">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-lg font-bold text-gold-300">{{ SessionTime::hour($consultation->starts_at) }} <span class="text-sm font-medium text-slate-400">– {{ SessionTime::hour($consultation->ends_at) }}</span></p>
            @if ($showDay ?? false)
                <p class="text-xs text-slate-400">{{ ConsultationTime::day($consultation) }}</p>
            @endif
            <p class="mt-1 font-semibold text-white">{{ $consultation->name }}</p>
            <p class="text-sm font-semibold text-gold-200" data-test="consultation-requester">{{ $consultation->requesterSummary() }}</p>
            <p class="text-sm text-slate-300">{{ $consultation->email }}</p>
            <p class="text-xs text-slate-500">{{ PhoneNumber::format($consultation->phone) }} · réservé le {{ $consultation->created_at?->translatedFormat('j F à H\hi') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($consultation->isCancelled())
                <span class="rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-200 ring-1 ring-rose-400/40">Annulée{{ $consultation->cancelled_by === 'visitor' ? ' par le visiteur' : '' }}</span>
            @endif
            @if ($consultation->attendance)
                <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold ring-1', 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/40' => $consultation->attendance === ConsultationAttendance::Present, 'bg-amber-400/15 text-amber-100 ring-amber-400/40' => $consultation->attendance === ConsultationAttendance::Absent])>{{ $consultation->attendance->label() }}</span>
            @endif
            @if ($consultation->user)
                <span class="rounded-full bg-electric-500/15 px-2.5 py-1 text-xs font-semibold text-electric-200 ring-1 ring-electric-400/40">Compte élève</span>
            @endif
        </div>
    </div>

    <dl class="mt-3 space-y-1.5 text-sm">
        <div><dt class="inline text-slate-400">Profil :</dt> <dd class="inline text-slate-100">{{ $consultation->requester_type->label() }}</dd></div>
        @if ($consultation->requester_type->isOrganization() && filled($consultation->organization_name))
            <div><dt class="inline text-slate-400">Structure :</dt> <dd class="inline text-slate-100">{{ $consultation->organization_name }}</dd></div>
        @endif
        <div><dt class="inline text-slate-400">Besoin :</dt> <dd class="inline text-slate-100">{{ $consultation->topic }}</dd></div>
        @if ($consultation->formation)
            <div><dt class="inline text-slate-400">Formation qui l'intéresse :</dt> <dd class="inline text-slate-100">{{ $consultation->formation->title }}</dd></div>
        @endif
        @if ($consultation->cancellation_message)
            <div><dt class="inline text-slate-400">Message d'annulation :</dt> <dd class="inline text-slate-100">{{ $consultation->cancellation_message }}</dd></div>
        @endif
    </dl>

    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn !gap-1.5 !px-4 !py-2 !text-sm bg-[#25D366]/15 text-[#7ee2a8] ring-1 ring-[#25D366]/40 hover:bg-[#25D366]/25"><x-site.whatsapp-icon class="size-4 shrink-0" /> WhatsApp</a>
        @if (! $consultation->isCancelled())
            @foreach (ConsultationAttendance::cases() as $case)
                <form method="POST" action="{{ route('admin.consultations.attendance', $consultation) }}">
                    @csrf
                    <input type="hidden" name="attendance" value="{{ $case->value }}">
                    <button type="submit" @class(['rounded-full px-4 py-2 text-sm font-semibold ring-1 transition', 'bg-veil/10 text-white ring-veil/30' => $consultation->attendance === $case, 'text-slate-300 ring-veil/15 hover:bg-veil/5' => $consultation->attendance !== $case])>{{ $case->label() }}</button>
                </form>
            @endforeach
        @endif
    </div>

    @if (! $consultation->isCancelled())
        {{-- Après l'appel : notes privées et formation recommandée --}}
        <details class="mt-4 group" @if ($consultation->admin_notes || $consultation->recommended_formation_id) open @endif>
            <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 text-sm font-semibold text-electric-300">Notes et recommandation <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" /></summary>
            <form method="POST" action="{{ route('admin.consultations.follow-up', $consultation) }}" class="mt-3 space-y-3">
                @csrf
                @method('PUT')
                <div class="space-y-1.5">
                    <label for="notes-{{ $consultation->id }}" class="field-label">Notes privées (jamais montrées au visiteur)</label>
                    <textarea id="notes-{{ $consultation->id }}" name="admin_notes" rows="3" class="field-input">{{ $consultation->admin_notes }}</textarea>
                </div>
                <div class="space-y-1.5">
                    <label for="reco-{{ $consultation->id }}" class="field-label">Formation recommandée</label>
                    <select id="reco-{{ $consultation->id }}" name="recommended_formation_id" class="field-input">
                        <option value="">Aucune pour l'instant</option>
                        @foreach ($formations as $formation)
                            <option value="{{ $formation->id }}" @selected($consultation->recommended_formation_id === $formation->id)>{{ $formation->title }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-ghost w-full !py-2.5 !text-sm sm:w-auto">Enregistrer</button>
            </form>
            @if ($consultation->recommendedFormation)
                <form method="POST" action="{{ route('admin.consultations.recommend', $consultation) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn-gold w-full !py-2.5 !text-sm sm:w-auto">Envoyer la recommandation</button>
                    <p class="mt-1 text-xs text-slate-500">
                        E-mail simple avec le lien vers « {{ $consultation->recommendedFormation->title }} ».
                        @if ($consultation->recommendation_sent_at)
                            Déjà envoyée le {{ $consultation->recommendation_sent_at->translatedFormat('j F à H\hi') }}.
                        @endif
                    </p>
                </form>
            @endif
            @error('recommendation'.$consultation->id)
                <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
            @enderror
        </details>
    @endif

    @if ($consultation->isBooked() && ! $consultation->isPast())
        <details class="mt-4 group" @if ($cancelErrors->any()) open @endif>
            <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 text-sm font-semibold text-rose-200">Annuler ce rendez-vous <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" /></summary>
            <form method="POST" action="{{ route('admin.consultations.cancel', $consultation) }}" class="mt-3 space-y-3">
                @csrf
                <label for="annulation-{{ $consultation->id }}" class="field-label">Message au visiteur (envoyé par e-mail)</label>
                <textarea id="annulation-{{ $consultation->id }}" name="message" rows="2" required class="field-input" placeholder="Exemple : un empêchement de dernière minute. Vous pouvez réserver un autre créneau.">{{ $cancelErrors->any() ? old('message') : '' }}</textarea>
                @if ($cancelErrors->has('message')) <p class="text-sm text-rose-300">{{ $cancelErrors->first('message') }}</p> @endif
                <button type="submit" class="btn w-full bg-rose-500 text-white hover:bg-rose-400 sm:w-auto">Confirmer l'annulation</button>
            </form>
        </details>
    @endif
</article>
