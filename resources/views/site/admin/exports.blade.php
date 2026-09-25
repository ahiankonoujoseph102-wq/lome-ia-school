<x-admin.page title="Exports CSV" subtitle="Fichiers à ouvrir dans Excel ou Google Sheets (accents corrects, séparateur « ; »). Chaque export est noté dans le journal d'activité.">
    <form method="GET" action="{{ route('admin.exports.download', 'ventes') }}" class="card space-y-4 p-5 sm:p-6">
        <h2 class="flex items-center gap-2 font-semibold text-white"><flux:icon.receipt-percent class="size-5 text-electric-300" /> Ventes</h2>
        <p class="text-sm text-slate-400">Tous les paiements (validés, provisoires, en attente, refusés, remboursés) et les accès manuels, avec remises et codes promo.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="from" label="Du (facultatif)" type="date" />
            <x-admin.field name="to" label="Au (facultatif)" type="date" />
        </div>
        <button type="submit" class="btn-gold w-full sm:w-auto"><flux:icon.arrow-down-tray class="size-5" /> Télécharger les ventes</button>
    </form>

    <div class="grid gap-3 sm:grid-cols-2">
        @foreach (['eleves' => 'users', 'commissions' => 'share', 'retraits' => 'arrow-up-tray', 'consultations' => 'video-camera'] as $type => $icon)
            <a href="{{ route('admin.exports.download', $type) }}" class="card flex items-center gap-3 p-4 font-semibold text-slate-200 hover:bg-veil/5">
                <flux:icon :name="$icon" class="size-5 text-electric-300" /> {{ $types[$type] }}
                <flux:icon.arrow-down-tray class="ml-auto size-4 text-slate-500" />
            </a>
        @endforeach
    </div>
    <p class="text-xs text-slate-500">Astuce : depuis « Toutes les ventes » ou « Élèves », le bouton « Exporter » garde vos filtres.</p>
</x-admin.page>
