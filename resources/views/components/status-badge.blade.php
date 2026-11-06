@props(['status'])

@php
    $color = $status !== null && method_exists($status, 'color') ? $status->color() : 'zinc';

    $classes = match ($color) {
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'purple' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset '.$classes) }}>
    {{ $status !== null && method_exists($status, 'label') ? $status->label() : ($status ?? '—') }}
</span>
