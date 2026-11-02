@php
    use App\Http\Controllers\Admin\StudentController;
    use App\Support\PhoneNumber;
@endphp

<x-admin.page title="Élèves" :subtitle="$total.' comptes au total. Recherchez par nom, numéro WhatsApp ou e-mail.'" width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.exports.download', ['type' => 'eleves'] + array_filter($filters)) }}" class="btn-ghost"><flux:icon.arrow-down-tray class="size-5" /> Exporter</a>
    </x-slot:actions>

    <form method="GET" class="card grid gap-3 p-4 sm:grid-cols-3" role="search">
        <div class="sm:col-span-3">
            <x-admin.field name="q" label="Rechercher" type="search" :value="$filters['q'] ?? ''" placeholder="Nom, WhatsApp ou e-mail" />
        </div>
        <x-admin.field name="filtre" label="Filtre" type="select" :options="StudentController::FILTERS" :value="$filters['filtre'] ?? ''" />
        <x-admin.field name="formation" label="Inscrits à" type="select" :options="['' => 'Toutes les formations'] + $formations" :value="$filters['formation'] ?? ''" />
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-gold flex-1">Rechercher</button>
            <a href="{{ route('admin.students.index') }}" class="btn-ghost" aria-label="Effacer les filtres"><flux:icon.x-mark class="size-5" /></a>
        </div>
    </form>

    <p class="text-sm text-slate-400">{{ $students->total() }} {{ $students->total() > 1 ? 'résultats' : 'résultat' }}</p>

    <ul class="space-y-2">
        @forelse ($students as $student)
            <li>
                <a href="{{ route('admin.students.show', $student) }}" class="card flex items-center gap-3 p-4 hover:bg-veil/5" data-test="student-row">
                    <x-site.avatar :user="$student" class="size-11 shrink-0" />
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2 font-semibold text-white">
                            <span class="truncate">{{ $student->name }}</span>
                            @if ($student->isStaff())
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-electric-200 ring-1 ring-electric-400/50">{{ $student->role->label() }}</span>
                            @endif
                            @if ($student->isBlocked())
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-rose-200 ring-1 ring-rose-400/50">Bloqué</span>
                            @endif
                        </span>
                        <span class="block truncate text-sm text-slate-400">{{ PhoneNumber::format($student->phone) }} · {{ $student->email }}</span>
                        <span class="block text-xs text-slate-400">{{ $student->enrollments_count }} {{ $student->enrollments_count > 1 ? 'formations' : 'formation' }} · inscrit le {{ $student->created_at?->translatedFormat('j M Y') }}</span>
                    </span>
                    <flux:icon.chevron-right class="size-4 shrink-0 text-slate-500" />
                </a>
            </li>
        @empty
            <li class="card p-6 text-center text-slate-400">Aucun compte ne correspond à cette recherche.</li>
        @endforelse
    </ul>

    {{ $students->links() }}
</x-admin.page>
