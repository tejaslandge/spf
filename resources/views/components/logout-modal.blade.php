<flux:modal name="logout-confirmation" class="max-w-md">
    <div class="space-y-6">
        <div class="flex items-start gap-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-inset ring-rose-600/10">
                <flux:icon.arrow-right-start-on-rectangle class="size-5" />
            </span>

            <div class="min-w-0">
                <flux:heading size="lg">{{ __('Log out of your account?') }}</flux:heading>
                <flux:text class="mt-1">{{ __('You will need to sign in again to access your workspace. Any unsaved changes will be lost.') }}</flux:text>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button type="submit" variant="danger" data-test="logout-button">
                    {{ __('Log out') }}
                </flux:button>
            </form>
        </div>
    </div>
</flux:modal>
