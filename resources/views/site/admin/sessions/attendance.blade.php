@php use App\LiveSessions\SessionTime; @endphp

<x-layouts::site title="Feuille de présence" noindex>
    <x-slot:head>
        <style>
            @media print {
                header, nav, footer, .no-print, [aria-label="Nous écrire sur WhatsApp"] { display: none !important; }
                html, body, main { background: #fff !important; color: #000 !important; }
                .print-sheet { color: #000 !important; }
                .print-sheet * { color: #000 !important; background: transparent !important; box-shadow: none !important; border-color: #999 !important; }
            }
        </style>
    </x-slot:head>

    <div class="print-sheet mx-auto max-w-3xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.sessions.show', $session) }}" class="no-print inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Retour à la session
        </a>

        <h1 class="mt-4 text-2xl font-bold text-white">Feuille de présence</h1>
        <p class="mt-1 text-slate-300">{{ $session->displayTitle() }} · {{ $session->formation->title }}</p>
        <p class="text-sm text-slate-400">{{ SessionTime::dayTitle($session) }}, {{ SessionTime::range($session) }} · {{ $session->location }}</p>

        @if (session('status'))
            <x-site.alert class="no-print mt-4">{{ session('status') }}</x-site.alert>
        @endif

        <div class="no-print mt-4 flex flex-wrap items-center gap-3">
            <button type="button" onclick="window.print()" class="btn-ghost !px-4 !py-2.5 !text-sm"><flux:icon.printer class="size-4" /> Imprimer</button>
            <span class="text-sm text-slate-400">{{ $registrations->whereNotNull('attended_at')->count() }} présent(s) sur {{ $registrations->count() }} inscrit(s)</span>
        </div>

        <table class="mt-5 w-full text-left text-sm" data-test="attendance-sheet">
            <thead>
                <tr class="border-b border-veil/15 text-xs tracking-wide text-slate-400 uppercase">
                    <th class="w-8 py-2 pr-2">N°</th>
                    <th class="py-2 pr-2">Nom</th>
                    <th class="hidden py-2 pr-2 sm:table-cell">Téléphone</th>
                    <th class="w-28 py-2 text-right">Présent</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registrations as $index => $registration)
                    <tr class="border-b border-veil/[0.06]">
                        <td class="py-3 pr-2 text-slate-400 tabular-nums">{{ $index + 1 }}</td>
                        <td class="py-3 pr-2">
                            <span class="font-medium text-white">{{ $registration->user->name }}</span>
                            <span class="block text-xs text-slate-400 sm:hidden">{{ $registration->user->formatted_phone }}</span>
                        </td>
                        <td class="hidden py-3 pr-2 text-slate-300 sm:table-cell">{{ $registration->user->formatted_phone }}</td>
                        <td class="py-3 text-right">
                            <form method="POST" action="{{ route('admin.sessions.attendance.toggle', [$session, $registration]) }}">
                                @csrf
                                <button type="submit" @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1',
                                    'bg-emerald-400/15 text-emerald-200 ring-emerald-400/40' => $registration->attended_at,
                                    'text-slate-300 ring-veil/20 hover:bg-veil/10' => ! $registration->attended_at,
                                ]) aria-pressed="{{ $registration->attended_at ? 'true' : 'false' }}">
                                    <span class="grid size-4 place-items-center rounded border border-current">@if ($registration->attended_at)✓@endif</span>
                                    {{ $registration->attended_at ? 'Présent' : 'Cocher' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-5 text-center text-slate-400">Aucun inscrit.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-6 hidden text-xs print:block">Signature du formateur : ______________________</p>
    </div>
</x-layouts::site>
