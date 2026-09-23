@props(['type' => 'success'])

<div {{ $attributes->class([
    'flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm',
    'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' => $type === 'success',
    'border-rose-400/30 bg-rose-400/10 text-rose-200' => $type === 'error',
    'border-electric-400/30 bg-electric-500/10 text-electric-200' => $type === 'info',
]) }} role="status">
    @if ($type === 'error')
        <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0" />
    @elseif ($type === 'info')
        <flux:icon.information-circle class="mt-0.5 size-5 shrink-0" />
    @else
        <flux:icon.check-circle class="mt-0.5 size-5 shrink-0" />
    @endif
    <div>{{ $slot }}</div>
</div>
