{{-- Alerte en haut des pages admin tant que le lien Google Meet fixe des consultations n'est pas renseigné. --}}
@php
    $showMeetAlert = request()->routeIs('admin.*')
        && auth()->user()?->isAdmin()
        && blank(\App\Models\ConsultationSettings::current()->meet_url);
@endphp

@if ($showMeetAlert)
    <div class="mx-auto max-w-4xl px-4 pt-4 sm:px-6" data-test="meet-missing-alert">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border-2 border-gold-400 bg-gold-400/15 px-4 py-3 text-sm text-gold-100" role="alert">
            <flux:icon.exclamation-triangle class="size-6 shrink-0 text-gold-300" />
            <p class="min-w-52 flex-1">
                <span class="block font-bold text-white">Lien Google Meet de la consultation non renseigné</span>
                Les visiteurs qui réservent ne reçoivent pas encore le lien de l'appel vidéo.
            </p>
            @unless (request()->routeIs('admin.consultations.availability'))
                <a href="{{ route('admin.consultations.availability') }}#meet_url" class="inline-flex items-center gap-1 rounded-full px-4 py-2 font-semibold text-gold-100 ring-2 ring-gold-400 hover:bg-gold-400/20">Renseigner le lien <flux:icon.arrow-right class="size-4" /></a>
            @endunless
        </div>
    </div>
@endif
