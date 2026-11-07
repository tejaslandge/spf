<?php

use App\Enums\AdminDesignation;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User & Permissions')] class extends Component
{
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showPermissionsModal = false;

    public bool $showActivityModal = false;

    public string $name = '';

    public string $email = '';

    public string $mobile = '';

    public string $role = 'admin';

    public string $designation = '';

    public bool $applyTemplate = true;

    public ?int $editingId = null;

    public ?int $permissionsUserId = null;

    public ?int $activityUserId = null;

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    #[Computed]
    public function users()
    {
        return User::query()->orderByDesc('role')->latest()->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AuditLog>
     */
    #[Computed]
    public function activityLogs()
    {
        if ($this->activityUserId === null) {
            return AuditLog::query()->whereRaw('1 = 0')->get();
        }

        return AuditLog::query()
            ->where('user_id', $this->activityUserId)
            ->latest()
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function editTarget(): ?User
    {
        return $this->editingId === null ? null : User::query()->find($this->editingId);
    }

    #[Computed]
    public function permissionsTarget(): ?User
    {
        return $this->permissionsUserId === null ? null : User::query()->find($this->permissionsUserId);
    }

    #[Computed]
    public function activityTarget(): ?User
    {
        return $this->activityUserId === null ? null : User::query()->find($this->activityUserId);
    }

    /**
     * @return array<string, array{label: string, permissions: array<int, array{value: string, label: string}>}>
     */
    #[Computed]
    public function permissionGroups(): array
    {
        return Permission::groupedOptions();
    }

    public function openCreateModal(): void
    {
        $this->authorizeSuperAdmin();

        $this->reset(['name', 'email', 'mobile', 'designation', 'editingId']);
        $this->role = 'admin';
        $this->applyTemplate = true;
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $this->authorizeSuperAdmin();

        $this->name = trim($this->name);
        $this->email = strtolower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'mobile' => ['required', 'digits:10', Rule::unique('users', 'mobile')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'designation' => ['nullable', Rule::enum(AdminDesignation::class)],
            'applyTemplate' => ['boolean'],
        ]);

        $designation = filled($validated['designation'] ?? null)
            ? AdminDesignation::from($validated['designation'])
            : null;

        $permissions = ($designation !== null && $validated['applyTemplate'])
            ? $designation->permissionValues()
            : [];

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'],
            'role' => UserRole::from($validated['role']),
            'designation' => $designation,
            'permissions' => $permissions,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        AuditLogger::log('admin.created', $user, [], [
            'role' => $user->role->value,
            'designation' => $user->designation?->value,
            'permissions' => $permissions,
        ]);

        $this->showCreateModal = false;
        unset($this->users);

        Flux::toast(variant: 'success', text: __('Team member created.'));
    }

    public function openEditModal(int $userId): void
    {
        $this->authorizeSuperAdmin();

        $user = User::query()->findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile = (string) $user->mobile;
        $this->designation = $user->designation?->value ?? '';
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function update(): void
    {
        $this->authorizeSuperAdmin();

        $user = $this->editTarget;

        abort_if($user === null, 404);

        $this->name = trim($this->name);
        $this->email = strtolower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['required', 'digits:10', Rule::unique('users', 'mobile')->ignore($user->id)],
            'designation' => ['nullable', Rule::enum(AdminDesignation::class)],
        ]);

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'designation' => $user->designation?->value,
        ];

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'],
            'designation' => filled($validated['designation'] ?? null) ? AdminDesignation::from($validated['designation']) : null,
        ]);

        AuditLogger::log('admin.updated', $user, $old, [
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'designation' => $user->designation?->value,
        ]);

        $this->showEditModal = false;
        unset($this->users, $this->editTarget);

        Flux::toast(variant: 'success', text: __('Team member updated.'));
    }

    public function openPermissionsModal(int $userId): void
    {
        $this->authorizeSuperAdmin();

        $user = User::query()->findOrFail($userId);

        abort_if($user->isSuperAdmin(), 403, __('Super Admin access cannot be restricted.'));

        $this->permissionsUserId = $user->id;
        $this->selectedPermissions = $user->permissionValues();
        $this->resetValidation();
        $this->showPermissionsModal = true;
    }

    public function applyDesignationTemplate(): void
    {
        $user = $this->permissionsTarget;

        abort_if($user === null, 404);

        if ($user->designation === null) {
            Flux::toast(variant: 'danger', text: __('Assign a designation before applying a template.'));

            return;
        }

        $this->selectedPermissions = $user->designation->permissionValues();

        Flux::toast(variant: 'info', text: __('Template applied. Review and save to confirm.'));
    }

    public function savePermissions(): void
    {
        $this->authorizeSuperAdmin();

        $user = $this->permissionsTarget;

        abort_if($user === null, 404);
        abort_if($user->isSuperAdmin(), 403, __('Super Admin access cannot be restricted.'));

        $valid = Permission::values();
        $permissions = array_values(array_intersect($this->selectedPermissions, $valid));
        $old = ['permissions' => $user->permissions ?? []];

        $user->update(['permissions' => $permissions]);

        AuditLogger::log('admin.permissions_updated', $user, $old, [
            'permissions' => $permissions,
            'designation' => $user->designation?->value,
        ]);

        $this->showPermissionsModal = false;
        unset($this->users, $this->permissionsTarget);

        Flux::toast(variant: 'success', text: __('Permissions updated.'));
    }

    public function toggleStatus(int $userId): void
    {
        $this->authorizeSuperAdmin();

        $user = User::query()->findOrFail($userId);

        if ($user->id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __('You cannot deactivate your own account.'));

            return;
        }

        if ($user->isSuperAdmin()) {
            $activeSuperAdmins = User::query()->where('role', UserRole::SuperAdmin)->where('status', 'active')->count();

            if ($activeSuperAdmins <= 1) {
                Flux::toast(variant: 'danger', text: __('At least one active Super Admin must remain.'));

                return;
            }
        }

        $old = ['status' => $user->status];

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);

        AuditLogger::log('admin.status_toggled', $user, $old, ['status' => $user->status]);

        unset($this->users);

        Flux::toast(variant: 'success', text: __('Access updated.'));
    }

    public function openActivityModal(int $userId): void
    {
        $this->authorizeSuperAdmin();

        $this->activityUserId = $userId;
        unset($this->activityLogs);
        $this->showActivityModal = true;
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Administration') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('User & Permissions') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Two roles only: Super Admin (full control) and Admin (granular, explicitly assigned permissions).') }}</p>
        </div>

        <button type="button" wire:click="openCreateModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
            <flux:icon.user-plus class="size-4" />
            {{ __('Add admin') }}
        </button>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Member') }}</th>
                        <th class="px-5 py-3">{{ __('Contact') }}</th>
                        <th class="px-5 py-3">{{ __('Role & designation') }}</th>
                        <th class="px-5 py-3">{{ __('Permissions') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3">{{ __('Last login') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->users as $user)
                        <tr class="transition hover:bg-slate-50/70" wire:key="user-{{ $user->id }}">
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
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $user->isSuperAdmin() ? 'bg-navy-50 text-navy-700 ring-navy-600/20' : 'bg-blue-50 text-blue-700 ring-blue-600/20' }}">
                                    {{ $user->role->label() }}
                                </span>
                                <p class="mt-1 text-[11px] text-slate-500">{{ $user->designation?->label() ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                @if ($user->isSuperAdmin())
                                    <span class="text-[11px] font-medium text-slate-500">{{ __('Unrestricted') }}</span>
                                @else
                                    <span class="text-xs font-semibold text-slate-700">{{ count($user->permissions ?? []) }}</span>
                                    <span class="text-[11px] text-slate-400">{{ __('assigned') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $user->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                    {{ $user->status === 'active' ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-3">
                                    <button type="button" wire:click="openEditModal({{ $user->id }})" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700">{{ __('Edit') }}</button>
                                    @unless ($user->isSuperAdmin())
                                        <button type="button" wire:click="openPermissionsModal({{ $user->id }})" class="text-[11px] font-semibold text-violet-600 hover:text-violet-700">{{ __('Permissions') }}</button>
                                    @endunless
                                    <button type="button" wire:click="openActivityModal({{ $user->id }})" class="text-[11px] font-semibold text-slate-500 hover:text-slate-700">{{ __('Activity') }}</button>
                                    <button type="button" wire:click="toggleStatus({{ $user->id }})" class="text-[11px] font-semibold {{ $user->status === 'active' ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                        {{ $user->status === 'active' ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-14 text-center text-sm text-slate-400">{{ __('No team members.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showCreateModal', false)"></div>

            <div class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <h3 class="text-base font-semibold">{{ __('Add admin account') }}</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="create" class="mt-4 space-y-3">
                    <flux:input wire:model="name" :label="__('Full name')" required minlength="2" maxlength="255" />
                    @error('name') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="email" type="email" :label="__('Email address')" required maxlength="255" />
                        <flux:input wire:model="mobile" :label="__('Mobile')" placeholder="10-digit mobile" inputmode="numeric" maxlength="10" required />
                    </div>
                    @error('email') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('mobile') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:select wire:model.live="role" :label="__('Role')" required>
                            <option value="admin">{{ __('Admin') }}</option>
                            <option value="super_admin">{{ __('Super Admin') }}</option>
                        </flux:select>
                        @if ($role === 'admin')
                            <flux:select wire:model.live="designation" :label="__('Designation')">
                                <option value="">{{ __('No designation') }}</option>
                                @foreach (\App\Enums\AdminDesignation::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </flux:select>
                        @endif
                    </div>

                    @if ($role === 'admin' && $designation !== '')
                        <label class="flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-medium text-slate-600">
                            <input type="checkbox" wire:model="applyTemplate" class="mt-0.5 rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                            <span>
                                {{ __('Apply the designation permission template now') }}
                                <span class="mt-0.5 block text-[11px] font-normal text-slate-400">{{ __('Permissions are only granted when this is applied; a designation alone never grants access. You can fine-tune afterwards.') }}</span>
                            </span>
                        </label>
                    @endif

                    <flux:input wire:model="password" type="password" :label="__('Temporary password')" viewable required />
                    <p class="text-[11px] text-slate-400">{{ __('The admin must change this password after signing in. Reset links are always available from this page.') }}</p>
                    @error('password') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Create account') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Edit modal --}}
    @if ($showEditModal && $this->editTarget)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showEditModal', false)"></div>

            <div class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <h3 class="text-base font-semibold">{{ __('Edit :name', ['name' => $this->editTarget->name]) }}</h3>
                    <button type="button" wire:click="$set('showEditModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="update" class="mt-4 space-y-3">
                    <flux:input wire:model="name" :label="__('Full name')" required minlength="2" maxlength="255" />
                    @error('name') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="email" type="email" :label="__('Email address')" required maxlength="255" />
                        <flux:input wire:model="mobile" :label="__('Mobile')" inputmode="numeric" maxlength="10" />
                    </div>
                    @error('email') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('mobile') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    @unless ($this->editTarget->isSuperAdmin())
                        <flux:select wire:model="designation" :label="__('Designation')">
                            <option value="">{{ __('No designation') }}</option>
                            @foreach (\App\Enums\AdminDesignation::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                        <p class="text-[11px] text-slate-400">{{ __('Changing the designation never changes permissions automatically.') }}</p>
                    @endunless

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showEditModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Permissions modal --}}
    @if ($showPermissionsModal && $this->permissionsTarget)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showPermissionsModal', false)"></div>

            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Permissions for :name', ['name' => $this->permissionsTarget->name]) }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ $this->permissionsTarget->designation?->label() ?? __('No designation') }}
                            · {{ count($selectedPermissions) }} {{ __('selected') }}
                        </p>
                    </div>
                    <button type="button" wire:click="$set('showPermissionsModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="applyDesignationTemplate"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                        <flux:icon.arrow-path class="size-3.5" />
                        {{ __('Apply designation template') }}
                    </button>
                    <p class="text-[11px] text-slate-400">{{ __('The template is a starting point — access is granted only when you save.') }}</p>
                </div>

                <form wire:submit="savePermissions" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($this->permissionGroups as $groupKey => $group)
                            <div class="rounded-xl border border-slate-200 p-4" wire:key="perm-group-{{ $groupKey }}">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $group['label'] }}</p>
                                <div class="mt-3 space-y-2">
                                    @foreach ($group['permissions'] as $permission)
                                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600" wire:key="perm-{{ $permission['value'] }}">
                                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission['value'] }}"
                                                   class="rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                                            {{ $permission['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showPermissionsModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Save permissions') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Activity modal --}}
    @if ($showActivityModal && $this->activityTarget)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showActivityModal', false)"></div>

            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Activity log') }} · {{ $this->activityTarget->name }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('Most recent 20 recorded actions.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showActivityModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-4 py-2.5">{{ __('Action') }}</th>
                                <th class="px-4 py-2.5">{{ __('Record') }}</th>
                                <th class="px-4 py-2.5">{{ __('IP') }}</th>
                                <th class="px-4 py-2.5">{{ __('When') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->activityLogs as $log)
                                <tr wire:key="activity-{{ $log->id }}">
                                    <td class="px-4 py-2.5 text-xs font-medium text-slate-700">{{ $log->action }}</td>
                                    <td class="px-4 py-2.5 text-[11px] text-slate-500">{{ class_basename((string) $log->auditable_type) }} #{{ $log->auditable_id ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-[11px] text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-[11px] text-slate-500">{{ $log->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-xs text-slate-400">{{ __('No activity recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
