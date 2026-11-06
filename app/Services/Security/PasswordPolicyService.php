<?php

namespace App\Services\Security;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class PasswordPolicyService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        // Until a Super Admin saves the policy, keep the application's
        // existing password behaviour (AppServiceProvider defaults).
        if (! $this->settings->isConfigured('security.password_min_length')) {
            return [Password::default()];
        }

        $password = Password::min(max(8, $this->settings->getInt('security.password_min_length', 12)));

        if ($this->settings->getBool('security.password_require_mixed_case', true)) {
            $password->mixedCase();
        }

        if ($this->settings->getBool('security.password_require_numbers', true)) {
            $password->numbers();
        }

        if ($this->settings->getBool('security.password_require_symbols', true)) {
            $password->symbols();
        }

        if ($this->settings->getBool('security.password_require_uncompromised', false)) {
            $password->uncompromised();
        }

        return [$password];
    }

    /**
     * @return array<int, mixed>
     */
    public function updateRules(): array
    {
        return ['required', 'string', ...$this->rules(), 'confirmed'];
    }

    public function expiryDays(): int
    {
        return max(0, $this->settings->getInt('security.password_expiry_days', 0));
    }

    public function historyCount(): int
    {
        return max(0, $this->settings->getInt('security.password_history_count', 0));
    }

    public function hasExpired(User $user): bool
    {
        $days = $this->expiryDays();

        if ($days === 0 || $user->password_changed_at === null) {
            return false;
        }

        return $user->password_changed_at->addDays($days)->isPast();
    }

    public function isReused(User $user, string $plainPassword): bool
    {
        $count = $this->historyCount();

        if ($count === 0) {
            return false;
        }

        $hashes = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($count)
            ->pluck('password');

        if ($hashes->contains(fn (string $hash): bool => Hash::check($plainPassword, $hash))) {
            return true;
        }

        return Hash::check($plainPassword, $user->password);
    }

    /**
     * Store the user's current hash in the history table and prune old rows.
     */
    public function record(User $user): void
    {
        PasswordHistory::query()->create([
            'user_id' => $user->id,
            'password' => $user->password,
        ]);

        $keep = $this->historyCount();

        $obsolete = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->skip(max($keep, 0))
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($obsolete->isNotEmpty()) {
            PasswordHistory::query()->whereIn('id', $obsolete)->delete();
        }
    }
}
