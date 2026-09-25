{{-- Lien admin « Consultations » avec le nombre de consultations du jour. --}}
@php
    $today = once(fn (): int => \App\Models\Consultation::query()->where('status', \App\Enums\ConsultationStatus::Booked)->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->count());
@endphp

<a href="{{ route('admin.consultations.index') }}" class="flex items-center gap-2 text-slate-200 hover:bg-veil/5 {{ $class ?? '' }}">
    <flux:icon.video-camera class="size-4" /> Consultations du jour
    <span @class([
        'ml-auto min-w-6 rounded-full px-2 py-0.5 text-center text-xs font-bold',
        'bg-gold-400 text-night-950' => $today > 0,
        'bg-veil/10 text-slate-400' => $today === 0,
    ]) aria-label="{{ $today }} aujourd'hui" data-test="consultations-today">{{ $today }}</span>
</a>
