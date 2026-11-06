<?php

use App\Enums\Permission;
use App\Models\SettingAuditLog;
use App\Models\User;
use App\Services\Security\SessionService;
use App\Services\Settings\SettingsService;
use App\Services\System\DatabaseBackupService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System & Security Settings')] class extends Component
{
    public string $passwordMinLength = '12';

    public bool $passwordRequireMixedCase = true;

    public bool $passwordRequireNumbers = true;

    public bool $passwordRequireSymbols = true;

    public bool $passwordRequireUncompromised = false;

    public string $passwordExpiryDays = '0';

    public string $passwordHistoryCount = '0';

    public bool $twoFactorEnabled = true;

    public bool $twoFactorRequiredSuperAdmin = false;

    public bool $twoFactorRequiredAdmin = false;

    public string $maxLoginAttempts = '5';

    public string $loginLockoutMinutes = '15';

    public string $sessionTimeoutMinutes = '0';

    public string $concurrentSessions = 'allow';

    public bool $auditLoggingEnabled = true;

    public bool $backupEnabled = false;

    public string $backupFrequency = 'daily';

    public string $backupRetentionDays = '30';

    public string $reason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        $settings = app(SettingsService::class);

        $this->passwordMinLength = (string) $settings->getInt('security.password_min_length', 12);
        $this->passwordRequireMixedCase = $settings->getBool('security.password_require_mixed_case', true);
        $this->passwordRequireNumbers = $settings->getBool('security.password_require_numbers', true);
        $this->passwordRequireSymbols = $settings->getBool('security.password_require_symbols', true);
        $this->passwordRequireUncompromised = $settings->getBool('security.password_require_uncompromised', false);
        $this->passwordExpiryDays = (string) $settings->getInt('security.password_expiry_days', 0);
        $this->passwordHistoryCount = (string) $settings->getInt('security.password_history_count', 0);
        $this->twoFactorEnabled = $settings->getBool('security.two_factor_enabled', true);
        $this->twoFactorRequiredSuperAdmin = $settings->getBool('security.two_factor_required_super_admin', false);
        $this->twoFactorRequiredAdmin = $settings->getBool('security.two_factor_required_admin', false);
        $this->maxLoginAttempts = (string) $settings->getInt('security.max_login_attempts', 5);
        $this->loginLockoutMinutes = (string) $settings->getInt('security.login_lockout_minutes', 15);
        $this->sessionTimeoutMinutes = (string) $settings->getInt('security.session_timeout_minutes', 0);
        $this->concurrentSessions = (string) $settings->get('security.concurrent_sessions', 'allow');
        $this->auditLoggingEnabled = $settings->getBool('security.audit_logging_enabled', true);
        $this->backupEnabled = $settings->getBool('backup.enabled', false);
        $this->backupFrequency = (string) $settings->get('backup.frequency', 'daily');
        $this->backupRetentionDays = (string) $settings->getInt('backup.retention_days', 30);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Support\SessionDevice>
     */
    #[Computed]
    public function mySessions()
    {
        return app(SessionService::class)->activeFor(auth()->user());
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    #[Computed]
    public function allSessions()
    {
        return app(SessionService::class)->allActive(25);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SettingAuditLog>
     */
    #[Computed]
    public function recentChanges()
    {
        return SettingAuditLog::query()
            ->with('changedBy')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function backupStatus(): array
    {
        $settings = app(SettingsService::class);

        return [
            'last_run_at' => $settings->get('backup.last_run_at'),
            'status' => $settings->get('backup.last_status', 'never'),
            'file' => $settings->get('backup.last_file'),
            'error' => $settings->get('backup.last_error'),
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        $validated = $this->validate([
            'passwordMinLength' => ['required', 'integer', 'min:8', 'max:64'],
            'passwordExpiryDays' => ['required', 'integer', 'min:0', 'max:730'],
            'passwordHistoryCount' => ['required', 'integer', 'min:0', 'max:24'],
            'maxLoginAttempts' => ['required', 'integer', 'min:1', 'max:20'],
            'loginLockoutMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'sessionTimeoutMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'concurrentSessions' => ['required', 'in:allow,single'],
            'backupFrequency' => ['required', 'in:daily,weekly,monthly'],
            'backupRetentionDays' => ['required', 'integer', 'min:1', 'max:365'],
            'passwordRequireMixedCase' => ['boolean'],
            'passwordRequireNumbers' => ['boolean'],
            'passwordRequireSymbols' => ['boolean'],
            'passwordRequireUncompromised' => ['boolean'],
            'twoFactorEnabled' => ['boolean'],
            'twoFactorRequiredSuperAdmin' => ['boolean'],
            'twoFactorRequiredAdmin' => ['boolean'],
            'auditLoggingEnabled' => ['boolean'],
            'backupEnabled' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = auth()->user();

        app(SettingsService::class)->putMany([
            'security.password_min_length' => (int) $validated['passwordMinLength'],
            'security.password_require_mixed_case' => $validated['passwordRequireMixedCase'],
            'security.password_require_numbers' => $validated['passwordRequireNumbers'],
            'security.password_require_symbols' => $validated['passwordRequireSymbols'],
            'security.password_require_uncompromised' => $validated['passwordRequireUncompromised'],
            'security.password_expiry_days' => (int) $validated['passwordExpiryDays'],
            'security.password_history_count' => (int) $validated['passwordHistoryCount'],
            'security.two_factor_enabled' => $validated['twoFactorEnabled'],
            'security.two_factor_required_super_admin' => $validated['twoFactorRequiredSuperAdmin'],
            'security.two_factor_required_admin' => $validated['twoFactorRequiredAdmin'],
            'security.max_login_attempts' => (int) $validated['maxLoginAttempts'],
            'security.login_lockout_minutes' => (int) $validated['loginLockoutMinutes'],
            'security.session_timeout_minutes' => (int) $validated['sessionTimeoutMinutes'],
            'security.concurrent_sessions' => $validated['concurrentSessions'],
            'security.audit_logging_enabled' => $validated['auditLoggingEnabled'],
            'backup.enabled' => $validated['backupEnabled'],
            'backup.frequency' => $validated['backupFrequency'],
            'backup.retention_days' => (int) $validated['backupRetentionDays'],
        ], $actor, $validated['reason'] ?: null);

        unset($this->recentChanges);

        $this->reason = '';

        Flux::toast(variant: 'success', text: __('Security settings saved. Policies apply immediately.'));
    }

    public function revokeSession(string $sessionId): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        if (app(SessionService::class)->revokeAnyExceptCurrent($sessionId)) {
            unset($this->mySessions, $this->allSessions);
            Flux::toast(variant: 'success', text: __('Session revoked.'));

            return;
        }

        Flux::toast(variant: 'danger', text: __('That session could not be revoked.'));
    }

    public function revokeOtherSessions(): void
    {
        $count = app(SessionService::class)->revokeOthers(auth()->user());

        unset($this->mySessions, $this->allSessions);

        Flux::toast(variant: 'success', text: trans_choice('{1} :count session revoked.|[2,*] :count sessions revoked.', $count, ['count' => $count]));
    }

    public function runBackup(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        $result = app(DatabaseBackupService::class)->run();

        unset($this->backupStatus);

        if ($result['status'] === 'success') {
            Flux::toast(variant: 'success', text: __('Backup completed: :file', ['file' => basename((string) $result['file'])]));

            return;
        }

        Flux::toast(variant: 'danger', text: __('Backup failed: :message', ['message' => $result['message']]));
    }
};
?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('System & Security Settings')" :subheading="__('Password policy, authentication rules, sessions, audit trail and database backups.')">
        <form wire:submit="save" class="space-y-6">
            {{-- Password policy --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.key class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Password policy') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Enforced on password change and reset. Passwords are always hashed.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <flux:input wire:model="passwordMinLength" :label="__('Minimum length')" type="number" min="8" max="64" required />
                        @error('passwordMinLength') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="passwordExpiryDays" :label="__('Password expiry (days, 0 = never)')" type="number" min="0" max="730" required />
                        @error('passwordExpiryDays') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="passwordHistoryCount" :label="__('Block reuse of last N passwords (0 = off)')" type="number" min="0" max="24" required />
                        @error('passwordHistoryCount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="passwordRequireMixedCase" />
                        {{ __('Require upper and lower case letters') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="passwordRequireNumbers" />
                        {{ __('Require numbers') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="passwordRequireSymbols" />
                        {{ __('Require special characters') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="passwordRequireUncompromised" />
                        {{ __('Reject known-compromised passwords (requires network check)') }}
                    </label>
                </div>
            </div>

            {{-- Authentication security --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.lock-closed class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Authentication security') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Two-factor requirements, login lockout and session lifetime.') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="twoFactorEnabled" />
                        {{ __('Two-factor authentication available') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="twoFactorRequiredSuperAdmin" />
                        {{ __('Mandatory 2FA for Super Admin accounts') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="twoFactorRequiredAdmin" />
                        {{ __('Mandatory 2FA for Admin accounts') }}
                    </label>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <flux:input wire:model="maxLoginAttempts" :label="__('Maximum login attempts')" type="number" min="1" max="20" required />
                        @error('maxLoginAttempts') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="loginLockoutMinutes" :label="__('Lockout duration (minutes)')" type="number" min="1" max="1440" required />
                        @error('loginLockoutMinutes') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="sessionTimeoutMinutes" :label="__('Session timeout (minutes, 0 = default)')" type="number" min="0" max="1440" required />
                        @error('sessionTimeoutMinutes') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select wire:model="concurrentSessions" :label="__('Concurrent session policy')">
                            <option value="allow">{{ __('Allow multiple sessions') }}</option>
                            <option value="single">{{ __('Single session per account') }}</option>
                        </flux:select>
                    </div>
                </div>
            </div>

            {{-- Audit trail --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                            <flux:icon.clipboard-document-list class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('Audit trail') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Every settings change records the actor, role, IP and reason.') }}</p>
                        </div>
                    </div>
                    <flux:switch wire:model="auditLoggingEnabled" />
                </div>

                @if (! $auditLoggingEnabled)
                    <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-[11px] font-medium text-amber-800">
                        {{ __('Audit logging is currently disabled. Financial and security actions will not be recorded.') }}
                    </div>
                @endif

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-4 py-2.5">{{ __('Setting') }}</th>
                                <th class="px-4 py-2.5">{{ __('Change') }}</th>
                                <th class="px-4 py-2.5">{{ __('By') }}</th>
                                <th class="px-4 py-2.5">{{ __('When') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->recentChanges as $change)
                                <tr>
                                    <td class="px-4 py-2.5 text-xs font-medium text-slate-700">{{ $change->setting_key }}</td>
                                    <td class="px-4 py-2.5 text-[11px] text-slate-500">
                                        <span class="text-slate-400">{{ \Illuminate\Support\Str::limit((string) $change->old_value, 24) ?: '—' }}</span>
                                        →
                                        <span class="text-slate-700">{{ \Illuminate\Support\Str::limit((string) $change->new_value, 24) ?: '—' }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-slate-600">{{ $change->changedBy?->name ?? __('System') }}</td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ $change->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-xs text-slate-400">{{ __('No settings changes recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Backup --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                            <flux:icon.circle-stack class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('Backup configuration') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Scheduled database dumps with retention pruning.') }}</p>
                        </div>
                    </div>
                    <flux:switch wire:model="backupEnabled" />
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <flux:select wire:model="backupFrequency" :label="__('Backup frequency')">
                            <option value="daily">{{ __('Daily') }}</option>
                            <option value="weekly">{{ __('Weekly') }}</option>
                            <option value="monthly">{{ __('Monthly') }}</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:input wire:model="backupRetentionDays" :label="__('Retention period (days)')" type="number" min="1" max="365" required />
                        @error('backupRetentionDays') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end pb-1">
                        <button type="button" wire:click="runBackup" wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 disabled:opacity-50">
                            <flux:icon.arrow-down-tray class="size-4" />
                            <span wire:loading.remove wire:target="runBackup">{{ __('Run backup now') }}</span>
                            <span wire:loading wire:target="runBackup">{{ __('Running…') }}</span>
                        </button>
                    </div>
                </div>

                @php($backup = $this->backupStatus)
                <div class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-600 sm:grid-cols-3">
                    <div>
                        <p class="font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Last backup') }}</p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $backup['last_run_at'] ? \Illuminate\Support\Carbon::parse($backup['last_run_at'])->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat()) : __('Never run') }}
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Status') }}</p>
                        <p class="mt-1 font-medium {{ $backup['status'] === 'success' ? 'text-emerald-700' : ($backup['status'] === 'failed' ? 'text-red-700' : 'text-slate-700') }}">
                            {{ ucfirst((string) $backup['status']) }}
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Latest file') }}</p>
                        <p class="mt-1 truncate font-medium text-slate-700" title="{{ $backup['file'] }}">{{ $backup['file'] ? basename((string) $backup['file']) : '—' }}</p>
                    </div>
                    @if ($backup['error'])
                        <div class="sm:col-span-3">
                            <p class="font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Last error') }}</p>
                            <p class="mt-1 font-medium text-red-700">{{ $backup['error'] }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <flux:textarea wire:model="reason" :label="__('Reason for this change (optional)')" rows="2" maxlength="500"
                    placeholder="{{ __('Example: raised minimum password length after security review') }}" />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.check class="size-4" />
                    {{ __('Save security settings') }}
                </button>
            </div>
        </form>

        {{-- Sessions (outside the save form) --}}
        <div class="mt-6 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                            <flux:icon.computer-desktop class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('My active sessions') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Devices signed in with your account.') }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="revokeOtherSessions"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                        {{ __('Sign out other sessions') }}
                    </button>
                </div>

                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($this->mySessions as $session)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="session-{{ $session->id }}">
                            <div>
                                <p class="text-xs font-semibold text-slate-700">
                                    {{ $session->browser() }} · {{ $session->platform() }}
                                    @if ($session->isCurrent)
                                        <span class="ms-2 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">{{ __('This device') }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ $session->ipAddress ?? __('unknown IP') }} · {{ __('Last active :time', ['time' => $session->lastActivity->diffForHumans()]) }}
                                </p>
                            </div>
                            @unless ($session->isCurrent)
                                <button type="button" wire:click="revokeSession('{{ $session->id }}')"
                                        class="text-[11px] font-semibold text-rose-600 transition hover:text-rose-700">
                                    {{ __('Revoke') }}
                                </button>
                            @endunless
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-slate-400">{{ __('No database-backed sessions found.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.users class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('All active sessions') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Recent sign-ins across the team. Revoke anything suspicious.') }}</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-4 py-2.5">{{ __('User') }}</th>
                                <th class="px-4 py-2.5">{{ __('IP') }}</th>
                                <th class="px-4 py-2.5">{{ __('Last activity') }}</th>
                                <th class="px-4 py-2.5 text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->allSessions as $session)
                                <tr wire:key="all-session-{{ $session->id }}">
                                    <td class="px-4 py-2.5 text-xs text-slate-700">{{ $session->user_name ?? __('Guest') }}</td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ $session->ip_address ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::createFromTimestamp((int) $session->last_activity)->diffForHumans() }}</td>
                                    <td class="px-4 py-2.5 text-end">
                                        <button type="button" wire:click="revokeSession('{{ $session->id }}')"
                                                class="text-[11px] font-semibold text-rose-600 transition hover:text-rose-700">
                                            {{ __('Revoke') }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-xs text-slate-400">{{ __('No sessions recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
