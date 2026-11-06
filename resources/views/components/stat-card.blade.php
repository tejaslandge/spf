@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'chart-bar',
    'tone' => 'blue',
    'trend' => null,
])

@php
    $toneClasses = match ($tone) {
        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/10',
        'rose' => 'bg-rose-50 text-rose-600 ring-rose-600/10',
        'navy' => 'bg-navy-50 text-navy-700 ring-navy-600/10',
        default => 'bg-blue-50 text-blue-600 ring-blue-600/10',
    };
@endphp

<div {{ $attributes->class('fc-card p-5') }}>
    <div class="flex items-start justify-between gap-3">
        <span class="flex size-10 items-center justify-center rounded-xl ring-1 {{ $toneClasses }}">
            <x-dynamic-component :component="'flux::icon.'.$icon" class="size-5" />
        </span>

        @if ($trend)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                <flux:icon.arrow-trending-up class="size-3" />
                {{ $trend }}
            </span>
        @endif
    </div>

    <p class="mt-4 text-xs font-medium text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-[11px] text-slate-400">{{ $hint }}</p>
    @endif
</div>
