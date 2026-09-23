<div class="card relative overflow-hidden p-6 text-center sm:p-10">
    <div class="glow pointer-events-none absolute inset-0 opacity-60"></div>
    <div class="relative">
        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-linear-to-br from-electric-500 to-electric-700 text-white shadow-lg shadow-electric-700/40">
            <flux:icon.sparkles class="size-8" />
        </span>
        <h3 class="mt-5 text-xl font-bold text-white">Votre première formation vous attend</h3>
        <p class="mx-auto mt-2 max-w-md text-slate-400">
            Choisissez une compétence IA à maîtriser : vous y accédez dès le paiement confirmé, avec un groupe privé pour vous accompagner.
        </p>
        <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('formations.index') }}" class="btn-gold">Découvrir le catalogue <flux:icon.arrow-right class="size-5" /></a>
            <a href="{{ route('consultation') }}" class="btn-ghost">Me faire conseiller gratuitement</a>
        </div>
    </div>
</div>
