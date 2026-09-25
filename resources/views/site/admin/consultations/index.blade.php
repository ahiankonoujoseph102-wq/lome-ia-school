@php
    use Carbon\CarbonImmutable;
    use Illuminate\Support\Str;
@endphp

<x-layouts::site title="Consultations" noindex>
    <div class="mx-auto max-w-4xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Administration
        </a>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Consultations gratuites</h1>
                <p class="mt-1 text-slate-400">Rendez-vous de 30 minutes, en appel vidéo. Heures de Lomé (GMT).</p>
            </div>
            <a href="{{ route('admin.consultations.availability') }}" class="btn-ghost !px-4 !py-2 !text-sm"><flux:icon.clock class="size-4" /> Disponibilités</a>
        </div>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif
        @error('consultation')
            <x-site.alert type="error" class="mt-5">{{ $message }}</x-site.alert>
        @enderror

        <h2 class="mt-8 text-lg font-bold text-white">À venir</h2>
        <div class="mt-3 space-y-8" data-test="upcoming-consultations">
            @forelse ($upcoming as $date => $consultations)
                @php $day = CarbonImmutable::parse($date); @endphp
                <section>
                    <h3 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-slate-300 uppercase">
                        {{ $day->isToday() ? 'Aujourd\'hui' : ($day->isTomorrow() ? 'Demain' : '') }}
                        <span class="text-slate-500 normal-case">{{ Str::ucfirst($day->translatedFormat('l j F')) }}</span>
                        <span class="rounded-full bg-veil/10 px-2 py-0.5 text-xs text-slate-300">{{ $consultations->count() }}</span>
                    </h3>
                    <div class="mt-3 space-y-3">
                        @foreach ($consultations as $consultation)
                            @include('site.admin.consultations.card')
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="card p-6 text-center text-sm text-slate-400">Aucune consultation à venir. Vous recevez un e-mail à chaque nouvelle réservation.</div>
            @endforelse
        </div>

        <h2 class="mt-12 text-lg font-bold text-white">Passées et annulées</h2>
        <div class="mt-3 space-y-3" data-test="past-consultations">
            @forelse ($past as $consultation)
                @include('site.admin.consultations.card', ['showDay' => true])
            @empty
                <div class="card p-6 text-center text-sm text-slate-400">Aucune consultation passée.</div>
            @endforelse
        </div>
        <div class="mt-6">{{ $past->links() }}</div>
    </div>
</x-layouts::site>
