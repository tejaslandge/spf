<?php

use App\Models\AuditLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit Logs')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $action = '';

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function logs()
    {
        return AuditLog::query()
            ->with('user')
            ->when($this->action !== '', fn ($query) => $query->where('action', $this->action))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.strtolower($this->search).'%';
                $query->where(fn ($query) => $query
                    ->whereRaw('LOWER(auditable_type) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(action) LIKE ?', [$term])
                    ->orWhereHas('user', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', [$term])));
            })
            ->latest()
            ->paginate(20);
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div>
        <p class="fc-eyebrow">{{ __('Governance') }}</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Audit Logs') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Every create, update and delete across financial records is recorded here.') }}</p>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_auto]">
            <div class="relative">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search model, action or user…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <select wire:model.live="action" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                <option value="">{{ __('All actions') }}</option>
                <option value="created">{{ __('Created') }}</option>
                <option value="updated">{{ __('Updated') }}</option>
                <option value="deleted">{{ __('Deleted') }}</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Time') }}</th>
                        <th class="px-5 py-3">{{ __('User') }}</th>
                        <th class="px-5 py-3">{{ __('Action') }}</th>
                        <th class="px-5 py-3">{{ __('Record') }}</th>
                        <th class="px-5 py-3">{{ __('IP address') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->logs as $log)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ $log->user?->name ?? __('System') }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $log->action === 'created' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : ($log->action === 'updated' ? 'bg-blue-50 text-blue-700 ring-blue-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20') }}">
                                    {{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ class_basename((string) $log->auditable_type) }} #{{ $log->auditable_id }}</td>
                            <td class="px-5 py-3 text-xs text-slate-400">{{ $log->ip_address ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-14 text-center text-sm text-slate-400">{{ __('No audit records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->logs->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->logs->links() }}</div>
        @endif
    </div>
</div>
