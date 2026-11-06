<?php

namespace App\Models;

use App\Enums\AdminDesignation;
use App\Enums\Permission;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $mobile
 * @property string $role
 * @property AdminDesignation|null $designation
 * @property array<int, string>|null $permissions
 * @property string|null $avatar_path
 * @property string $status
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $password_changed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'mobile', 'avatar_path', 'password', 'role', 'designation', 'permissions', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'designation' => AdminDesignation::class,
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    /**
     * Super Admin has unrestricted access; every other user only receives the
     * permissions explicitly assigned by a Super Admin.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $value = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($value, $this->permissions ?? [], true);
    }

    /**
     * @param  array<int, Permission|string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return $this->isSuperAdmin()
            ? Permission::values()
            : array_values($this->permissions ?? []);
    }

    public function designationLabel(): string
    {
        return $this->designation?->label() ?? ($this->isSuperAdmin() ? 'Super Admin' : 'Admin');
    }

    public function dashboardRoute(): string
    {
        return $this->role->dashboardRoute();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function commissionProfile(): HasOne
    {
        return $this->hasOne(AdminCommissionProfile::class);
    }

    public function adminCommissions(): HasMany
    {
        return $this->hasMany(InterestDistributionLedger::class, 'admin_id');
    }

    public function commissionPayouts(): HasMany
    {
        return $this->hasMany(AdminCommissionPayout::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
