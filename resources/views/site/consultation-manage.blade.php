@php
    use App\Consultations\ConsultationTime;
    use App\Models\ConsultationSettings;
    use App\Support\PhoneNumber;
    use App\Support\WhatsApp;

    $deadlineHours = ConsultationSettings::current()->manage_deadline_hours;
    $isUpcoming = $consultation->isBooked() && ! $consultation->isPast();
@endphp

<x-layouts::site title="Ma consultation gratuite" noindex :whatsapp-message="config('site.whatsapp.consultation_message')">
    <div class="mx-auto max-w-2xl space-y-5 px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        @if (session('booked'))
            <div class="card border-emerald-400/30 bg-emerald-400/10 p-5 text-center" data-test="booking-confirmed">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-emerald-400/20 text-emerald-300"><flux:icon.check class="size-6" /></span>
                <h1 class="mt-3 text-2xl font-bold text-white">Votre consultation est confirmée</h1>
                <p class="mt-1 text-sm text-slate-300">Un e-mail de confirmation vous a été envoyé à {{ $consultation->email }}.</p>
            </div>
        @else
            <h1 class="text-2xl font-bold text-white sm:text-3xl">Ma consultation gratuite</h1>
        @endif

        @if (session('status'))
            <x-site.alert>{{ session('status') }}</x-site.alert>
        @endif
        @error('starts_at')
            <x-site.alert type="error">{{ $message }}</x-site.alert>
        @enderror
        @error('consultation')
            <x-site.alert type="error">{{ $message }}</x-site.alert>
        @enderror

        {{-- Rendez-vous --}}
        <section class="card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span @class([
                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1',
                    'bg-emerald-400/15 text-emerald-200 ring-emerald-400/40' => $isUpcoming,
                    'bg-rose-400/15 text-rose-200 ring-rose-400/40' => $consultation->isCancelled(),
                    'bg-veil/10 text-slate-300 ring-veil/20' => $consultation->isBooked() && $consultation->isPast(),
                ])>{{ $consultation->isCancelled() ? 'Annulée' : ($consultation->isPast() ? 'Terminée' : 'Confirmée') }}</span>
                <span class="text-xs text-slate-400">30 minutes · en direct en ligne, par appel vidéo</span>
            </div>
            <p class="mt-3 text-xl font-bold text-white" data-test="consultation-day">{{ ConsultationTime::day($consultation) }}</p>
            <p class="text-slate-200">{{ ucfirst(ConsultationTime::range($consultation)) }}</p>
            @if (ConsultationTime::neighbours($consultation) !== '')
                <p class="text-sm text-slate-400">Soit {{ ConsultationTime::neighbours($consultation) }} · même heure qu'à Lomé au Burkina Faso</p>
            @endif

            @if ($consultation->isCancelled())
                @if ($consultation->cancellation_message)
                    <p class="mt-4 rounded-2xl bg-veil/5 p-4 text-sm text-slate-300 ring-1 ring-veil/10"><span class="font-semibold text-white">Message de Lomé IA School :</span> {{ $consultation->cancellation_message }}</p>
                @endif
                <a href="{{ route('consultation') }}#reserver" class="btn-gold mt-5 w-full">Réserver un autre créneau</a>
            @elseif ($isUpcoming)
                <div class="mt-5 rounded-2xl bg-electric-500/10 p-4 ring-1 ring-electric-400/30" data-test="meet-link">
                    <p class="font-semibold text-white">Lien de l'appel vidéo (Google Meet)</p>
                    @if ($meetUrl)
                        <p class="mt-1 font-mono text-sm break-all text-electric-200">{{ $meetUrl }}</p>
                        <a href="{{ $meetUrl }}" target="_blank" rel="noopener" class="btn-gold mt-4 w-full">Rejoindre l'appel vidéo</a>
                    @else
                        <p class="mt-1 text-sm text-slate-300">Le lien vous sera envoyé par e-mail avant le rendez-vous.</p>
                    @endif
                </div>
                <a href="{{ route('consultation.calendar', $token) }}" class="btn-ghost mt-3 w-full"><flux:icon.calendar-days class="size-5" /> Ajouter à mon agenda</a>

                <div class="mt-5">
                    <p class="font-semibold text-white">Pour bien se préparer</p>
                    <ul class="mt-2 space-y-2 text-sm text-slate-300">
                        <li class="flex gap-2"><flux:icon.check class="mt-0.5 size-4 shrink-0 text-gold-300" /> Installez-vous dans un endroit calme.</li>
                        <li class="flex gap-2"><flux:icon.check class="mt-0.5 size-4 shrink-0 text-gold-300" /> Vérifiez votre connexion internet et la batterie de votre téléphone.</li>
                        <li class="flex gap-2"><flux:icon.check class="mt-0.5 size-4 shrink-0 text-gold-300" /> Rejoignez l'appel à l'heure, depuis votre téléphone ou votre ordinateur.</li>
                    </ul>
                </div>
            @endif
        </section>

        {{-- Informations envoyées --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold text-white">Vos informations</h2>
            <dl class="mt-3 space-y-2 text-sm">
                @foreach (array_filter([
                    'Nom' => $consultation->name,
                    'WhatsApp' => PhoneNumber::format($consultation->phone),
                    'E-mail' => $consultation->email,
                    'Votre besoin' => $consultation->topic,
                    'Formation' => $consultation->formation?->title,
                ]) as $label => $value)
                    <div class="flex flex-wrap justify-between gap-x-4">
                        <dt class="text-slate-400">{{ $label }}</dt>
                        <dd class="text-right font-medium break-words text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Déplacer ou annuler --}}
        @if ($isUpcoming)
            @if ($consultation->canBeChangedByVisitor())
                <details class="card group p-5 sm:p-6" @if ($errors->has('starts_at')) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-white">
                        Déplacer mon rendez-vous
                        <flux:icon.chevron-down class="size-5 text-slate-400 transition group-open:rotate-180" />
                    </summary>
                    @if ($days === [])
                        <p class="mt-4 text-sm text-slate-400">Aucun autre créneau n'est disponible pour le moment.</p>
                    @else
                        <form method="POST" action="{{ route('consultation.move', $token) }}" class="mt-5 space-y-5" x-data="slotPicker(@js(['days' => $days, 'selected' => null]))">
                            @csrf
                            @method('PUT')
                            @include('site.partials.slot-picker', ['selected' => null])
                            <button type="submit" class="btn-gold w-full" :disabled="!slot" x-ref="next">Confirmer le nouveau créneau</button>
                        </form>
                    @endif
                </details>

                <details class="card group p-5 sm:p-6">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-rose-200">
                        Annuler mon rendez-vous
                        <flux:icon.chevron-down class="size-5 text-slate-400 transition group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('consultation.cancel', $token) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm text-slate-400">Le créneau sera proposé à d'autres personnes. Vous pourrez en réserver un autre quand vous voulez.</p>
                        <button type="submit" class="btn w-full bg-rose-500 text-white hover:bg-rose-400" data-test="cancel-consultation">Confirmer l'annulation</button>
                    </form>
                </details>

                <p class="text-center text-xs text-slate-500">Déplacement ou annulation possible jusqu'au {{ $consultation->changeDeadline()->translatedFormat('j F à H\hi') }} ({{ $deadlineHours }} heures avant). Gardez ce lien : il est aussi dans votre e-mail de confirmation.</p>
            @else
                <div class="card p-5 text-sm text-slate-300">
                    Le rendez-vous ne peut plus être déplacé ni annulé en ligne (c'est possible jusqu'à {{ $deadlineHours }} heures avant). En cas d'empêchement, prévenez-nous sur WhatsApp.
                    <a href="{{ WhatsApp::url('Bonjour Lomé IA School, j\'ai un empêchement pour ma consultation du '.ConsultationTime::short($consultation).'.') }}" target="_blank" rel="noopener" class="btn-ghost mt-4 w-full">Nous écrire sur WhatsApp</a>
                </div>
            @endif
        @endif
    </div>
</x-layouts::site>
