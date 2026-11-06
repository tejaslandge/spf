<?php

use App\Concerns\ProfileValidationRules;
use App\Support\AuditLogger;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My Profile')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $mobile = '';

    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile = (string) $user->mobile;
    }

    /**
     * Update the profile information for the currently authenticated user.
     *
     * Roles and account status are never writable from this screen.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));
        $validated['mobile'] = $validated['mobile'] ?: null;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'],
        ]);

        $changes = [];

        foreach (['name', 'email', 'mobile'] as $field) {
            if ($user->isDirty($field)) {
                $changes[$field] = true;
            }
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($changes === []) {
            Flux::toast(variant: 'info', text: __('No changes to save.'));

            return;
        }

        $oldValues = array_intersect_key($user->getOriginal(), $changes);
        $newValues = array_intersect_key($validated, $changes);

        $user->save();

        AuditLogger::log('profile.updated', $user, $oldValues, [
            ...$newValues,
            'role' => $user->role->value,
        ]);

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Store a new profile photo for the authenticated user.
     */
    public function updatePhoto(): void
    {
        $user = Auth::user();

        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $previous = $user->avatar_path;
        $path = $this->photo->store('avatars', 'local');

        $user->forceFill(['avatar_path' => $path])->save();

        if (is_string($previous) && $previous !== '' && Storage::disk('local')->exists($previous)) {
            Storage::disk('local')->delete($previous);
        }

        AuditLogger::log('profile.photo_updated', $user, [], []);

        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Profile photo updated.'));
    }

    /**
     * Remove the authenticated user's profile photo.
     */
    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->avatar_path !== null && Storage::disk('local')->exists($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => null])->save();

        AuditLogger::log('profile.photo_removed', $user, [], []);

        Flux::toast(variant: 'success', text: __('Profile photo removed.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }
}; ?>

@php
    $settingsHome = auth()->user()->isSuperAdmin() ? route('super-admin.settings') : route('admin.settings');
@endphp

<section class="w-full">
    <x-pages::settings.layout :heading="__('My Profile')" :subheading="__('Update your name, email address and mobile number.')">
        @if (session('status'))
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Photo and account meta --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div class="shrink-0">
                    @if (auth()->user()->avatar_path)
                        <img src="{{ route('profile.avatar') }}" alt="{{ auth()->user()->name }}" class="size-24 rounded-2xl object-cover shadow ring-1 ring-slate-200">
                    @else
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="xl" class="!size-24 !text-2xl" />
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Profile photo') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Shown in the sidebar and on activity records.') }}</p>

                    <form wire:submit="updatePhoto" class="mt-3 space-y-3">
                        <input type="file" id="photo" wire:model="photo" accept=".jpg,.jpeg,.png,.webp"
                               class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                        @error('photo') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 disabled:opacity-50">
                                <flux:icon.arrow-up-tray class="size-3.5" />
                                <span wire:loading.remove wire:target="updatePhoto">{{ __('Upload photo') }}</span>
                                <span wire:loading wire:target="updatePhoto">{{ __('Uploading…') }}</span>
                            </button>
                            @if (auth()->user()->avatar_path)
                                <button type="button" wire:click="removePhoto"
                                        class="text-xs font-semibold text-rose-600 transition hover:text-rose-700">
                                    {{ __('Remove photo') }}
                                </button>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-400">{{ __('JPG, PNG or WebP up to 2 MB.') }}</p>
                    </form>

                    <dl class="mt-5 grid gap-x-6 gap-y-2 border-t border-slate-100 pt-4 text-[11px] sm:grid-cols-3">
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Designation') }}</dt>
                            <dd class="mt-1 font-medium text-slate-700">{{ auth()->user()->designationLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Member since') }}</dt>
                            <dd class="mt-1 font-medium text-slate-700">{{ auth()->user()->created_at?->format(\App\Support\Regional::dateFormat()) ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Last sign in') }}</dt>
                            <dd class="mt-1 font-medium text-slate-700">{{ auth()->user()->last_login_at?->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat()) ?? __('Not recorded') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <form wire:submit="updateProfileInformation" class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.user class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Profile information') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Your role and account status cannot be changed here.') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-5">
                    <div>
                        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />
                        @error('name')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
                        @error('email')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($this->hasUnverifiedEmail)
                            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                                {{ __('Your email address is unverified.') }}
                                <button type="button" class="font-semibold text-amber-900 underline underline-offset-2 transition hover:text-amber-950" wire:click.prevent="resendVerificationNotification">
                                    {{ __('Re-send the verification email.') }}
                                </button>
                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-2 font-medium text-emerald-700">{{ __('A new verification link has been sent to your email address.') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div>
                        <flux:input wire:model="mobile" :label="__('Mobile number')" type="tel" inputmode="numeric" autocomplete="tel-national" placeholder="9876543210" maxlength="10" />
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('10-digit Indian mobile number. Used for EMI reminders and sign-in alerts.') }}</p>
                        @error('mobile')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ $settingsHome }}" wire:navigate
                   class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" data-test="update-profile-button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2">
                    <flux:icon.check class="size-4" />
                    {{ __('Save changes') }}
                </button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
