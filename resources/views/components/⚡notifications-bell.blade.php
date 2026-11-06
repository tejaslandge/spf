<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function markRead(string $notificationId): void
    {
        auth()->user()->notifications()
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }
};
?>

<flux:dropdown position="bottom" align="end">
    <button type="button" class="relative flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
        <flux:icon.bell class="size-4" />
        @if ($this->unreadCount > 0)
            <span class="absolute -end-1 -top-1 inline-flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white ring-2 ring-white">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
        <span class="sr-only">{{ __('Notifications') }}</span>
    </button>

    <flux:menu class="w-80">
        <div class="flex items-center justify-between px-2 py-1.5">
            <p class="text-xs font-semibold text-slate-900">{{ __('Notifications') }}</p>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-[11px] font-semibold text-navy-700 transition hover:text-navy-900">
                    {{ __('Mark all read') }}
                </button>
            @endif
        </div>

        <flux:menu.separator />

        <div class="max-h-80 overflow-y-auto">
            @forelse (auth()->user()->notifications()->latest()->limit(8)->get() as $notification)
                @php($data = $notification->data)
                <a href="{{ $data['url'] ?? route('dashboard') }}" wire:click="markRead('{{ $notification->id }}')"
                   class="flex items-start gap-3 rounded-lg px-2 py-2.5 transition hover:bg-slate-50 {{ $notification->read_at === null ? 'bg-navy-50/50' : '' }}"
                   wire:key="notification-{{ $notification->id }}">
                    <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg {{ $notification->read_at === null ? 'bg-navy-900 text-cyber-400' : 'bg-slate-100 text-slate-400' }}">
                        <flux:icon.bell-alert class="size-3.5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-semibold text-slate-800">{{ $data['title'] ?? __('Notification') }}</span>
                        <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-500">{{ \Illuminate\Support\Str::limit($data['body'] ?? '', 90) }}</span>
                        <span class="mt-1 block text-[10px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <div class="px-3 py-8 text-center">
                    <flux:icon.bell-slash class="mx-auto size-6 text-slate-300" />
                    <p class="mt-2 text-xs font-medium text-slate-500">{{ __('No notifications yet') }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Loan, deposit and KYC updates appear here.') }}</p>
                </div>
            @endforelse
        </div>

        <flux:menu.separator />

        <flux:menu.item :href="route('settings.notifications')" icon="cog-6-tooth" wire:navigate>
            {{ __('Notification preferences') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
