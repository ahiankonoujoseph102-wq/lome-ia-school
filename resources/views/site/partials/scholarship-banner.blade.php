{{--
    Bandeau de l'accueil vers la bourse « Créer des agents IA », affiché seulement pendant que les candidatures sont
    ouvertes (ScholarshipCampaign::OPEN) ; disparaît seul à la clôture ou quand toutes les places sont prises.
    Style stable face au mode sombre forcé : fond foncé, texte clair, filet doré dessiné en ombre.
--}}
@php
    use App\Scholarships\ScholarshipCampaign;
@endphp
@if (ScholarshipCampaign::isOpen())
    @php $left = array_sum(ScholarshipCampaign::remaining()); @endphp
    <a href="{{ route('scholarship') }}" class="group block bg-night-900 shadow-[inset_0_-1px_0_var(--color-gold-500)] hover:bg-night-800" data-test="home-scholarship">
        <span class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gold-400/10 px-2.5 py-1 text-xs font-bold text-gold-100 uppercase ring-1 ring-gold-400/50">
                <flux:icon.sparkles class="size-3.5" /> Bourse
            </span>
            <span class="min-w-0 flex-1 text-sm text-slate-200">
                <strong class="text-white">{{ ScholarshipCampaign::totalSeats() }} formations « Agents IA » offertes à 100&nbsp;%</strong>
                · {{ $left > 1 ? 'plus que '.$left.' places' : ($left === 1 ? 'plus qu\'1 place' : '') }}
            </span>
            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-gold-400/10 px-3 py-1.5 text-sm font-semibold text-gold-100 ring-1 ring-gold-400/60 group-hover:bg-gold-400/20">Je postule <flux:icon.arrow-right class="size-4" /></span>
        </span>
    </a>
@endif
