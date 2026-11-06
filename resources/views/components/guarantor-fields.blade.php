@props([
    'model',
    'row' => [],
    'index' => 0,
    'removable' => false,
])

@php
    $relationship = $row['relationship'] ?? '';
    $requiresCustomRelationship = $relationship === \App\Enums\RelationshipType::Other->value;
@endphp

<div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-semibold text-slate-700">{{ __('Guarantor :number', ['number' => $index + 1]) }}</p>

        @if ($removable)
            <button type="button" wire:click="removeGuarantorRow({{ $index }})" class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 transition hover:text-rose-700">
                <flux:icon.trash class="size-3.5" />
                {{ __('Remove') }}
            </button>
        @endif
    </div>

    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <flux:input wire:model="{{ $model }}.full_name" :label="__('Full name')" placeholder="As per identity document" maxlength="255" required />
        @error($model.'.full_name') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:input wire:model="{{ $model }}.mobile_number" :label="__('Mobile number')" placeholder="10-digit Indian mobile" inputmode="numeric" maxlength="10" required />
        @error($model.'.mobile_number') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:input wire:model="{{ $model }}.alternate_mobile" :label="__('Alternate mobile')" placeholder="Optional" inputmode="numeric" maxlength="10" />
        @error($model.'.alternate_mobile') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:input wire:model="{{ $model }}.email" :label="__('Email')" type="email" placeholder="Optional" maxlength="255" />
        @error($model.'.email') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <div class="sm:col-span-2">
            <flux:textarea wire:model="{{ $model }}.address" :label="__('Complete address')" rows="2" placeholder="House, street, area" maxlength="1000" required />
            @error($model.'.address') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <flux:input wire:model="{{ $model }}.city" :label="__('City')" maxlength="100" required />
        @error($model.'.city') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:input wire:model="{{ $model }}.state" :label="__('State')" maxlength="100" required />
        @error($model.'.state') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:input wire:model="{{ $model }}.pincode" :label="__('PIN code')" placeholder="6 digits" inputmode="numeric" maxlength="6" required />
        @error($model.'.pincode') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:select wire:model.live="{{ $model }}.relationship" :label="__('Relationship with borrower')" required>
            <option value="">{{ __('Select relationship') }}</option>
            @foreach (\App\Enums\RelationshipType::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </flux:select>
        @error($model.'.relationship') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        @if ($requiresCustomRelationship)
            <flux:input wire:model="{{ $model }}.custom_relationship" :label="__('Custom relationship')" placeholder="Describe the relationship" maxlength="100" required />
            @error($model.'.custom_relationship') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
        @endif

        <flux:input wire:model="{{ $model }}.occupation" :label="__('Occupation')" placeholder="Optional" maxlength="100" />
        @error($model.'.occupation') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:select wire:model="{{ $model }}.identity_document_type" :label="__('Identity document type')">
            <option value="">{{ __('Not provided') }}</option>
            @foreach (\App\Enums\IdentityDocumentType::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </flux:select>
        @error($model.'.identity_document_type') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror

        <flux:input wire:model="{{ $model }}.identity_document_number" :label="__('Identity document number')" placeholder="Stored encrypted" maxlength="50" autocomplete="off" />
        @error($model.'.identity_document_number') <p class="-mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>
</div>
