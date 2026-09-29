{{--
    « Ajouter à mon agenda » : Google Agenda (rendez-vous prérempli, le plus simple sur téléphone)
    et fichier .ics pour les autres agendas (iPhone, Outlook).
    Variables : $googleUrl, $icsUrl, $class (facultatif).
--}}
<div @class(['grid gap-2 sm:grid-cols-2', $class ?? '']) data-test="calendar-buttons">
    <p class="text-sm font-semibold text-white sm:col-span-2">Ajouter à mon agenda</p>
    <a href="{{ $googleUrl }}" target="_blank" rel="noopener" class="btn-ghost w-full" data-test="google-calendar-button">
        <flux:icon.calendar-days class="size-5 text-electric-300" /> Ajouter à Google Agenda
    </a>
    <a href="{{ $icsUrl }}" class="btn-ghost w-full" data-test="calendar-button">
        <flux:icon.arrow-down-tray class="size-5 text-slate-400" /> Agenda iPhone ou autre
    </a>
</div>
