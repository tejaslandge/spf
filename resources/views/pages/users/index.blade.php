<?php

use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Team & Access')] class extends Component
{
    public bool $showModal = false;

    public string $name = '';

    public string $email = '';

    public string $mobile = '';

    public string $role = 'admin';

    public string $password = '';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    #[Computed]
    public function users()
    {
        return User::query()->latest()->get();
    }

    public function openModal(): void
    {
        $this->reset(['name', 'email', 'mobile', 'password']);
        $this->role = 'admin';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->email = strtolower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'mobile' => ['nullable', 'digits:10', Rule::unique('users', 'mobile')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?: null,
            'role' => UserRole::from($validated['role']),
            'status' => 'active',
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $this->showModal = false;
        unset($this->users);
        Flux::toast(variant: 'success', text: __('Team member created.'));
    }

    public function toggleStatus(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __('You cannot deactivate your own account.'));

            return;
        }

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);

        unset($this->users);
        Flux::toast(variant: 'success', text: __('Access updated.'));
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Administration') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Team & Access') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Two roles only: Super Admin (full control) and Admin (daily operations).') }}</p>
        </div>

        <button type="button" wire:click="openModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
            <flux:icon.user-plus class="size-4" />
            {{ __('Add member') }}
        </button>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Member') }}</th>
                        <th class="px-5 py-3">{{ __('Contact') }}</th>
                        <th class="px-5 py-3">{{ __('Role') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3">{{ __('Last login') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->users as $user)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$user->name" :initials="str($user->name)->substr(0, 2)" size="sm" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-800">{{ $user->name }}</p>
                                        @if ($user->id === auth()->id())
                                            <p class="text-[11px] font-semibold text-blue-600">{{ __('You') }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-xs text-slate-600">{{ $user->email }}</p>
                                <p class="text-[11px] text-slate-400">{{ $user->mobile ?: '—' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $user->isSuperAdmin() ? 'bg-navy-50 text-navy-700 ring-navy-600/20' : 'bg-blue-50 text-blue-700 ring-blue-600/20' }}">
                                    {{ $user->role->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $user->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                    {{ $user->status === 'active' ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3 text-end">
                                <button type="button" wire:click="toggleStatus({{ $user->id }})" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700">
                                    {{ $user->status === 'active' ? __('Deactivate') : __('Activate') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400">{{ __('No team members.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <h3 class="text-base font-semibold">{{ __('Add team member') }}</h3>
                    <button type="button" wire:click="$set('showModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="save" class="mt-4 space-y-3">
                    <flux:input wire:model="name" :label="__('Full name')" required minlength="2" maxlength="255" />
                    <flux:input wire:model="email" type="email" :label="__('Email address')" required maxlength="255" />
                    <flux:input wire:model="mobile" :label="__('Mobile')" placeholder="10-digit mobile" inputmode="numeric" maxlength="10" />

                    <flux:select wire:model="role" :label="__('Role')" required>
                        <option value="admin">{{ __('Admin') }}</option>
                        <option value="super_admin">{{ __('Super Admin') }}</option>
                    </flux:select>

                    <flux:input wire:model="password" type="password" :label="__('Temporary password')" viewable required />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Create member') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
