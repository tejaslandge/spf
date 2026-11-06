<?php

use App\Models\Investor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function validInvestorPayload(): array
{
    return [
        'name' => 'Ramesh Patil',
        'address' => '12 MG Road, Pune, Maharashtra 411001',
        'mobile' => '9876543210',
        'email' => 'ramesh@example.com',
        'pan_number' => 'ABCDE1234F',
        'aadhaar_number' => '234567890123',
        'bank_name' => 'HDFC Bank',
        'bank_account_number' => '123456789012',
        'bank_ifsc' => 'HDFC0001234',
        'status' => 'active',
    ];
}

test('an investor can be created with aadhaar and pan documents', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::investors.form')
        ->set(validInvestorPayload())
        ->set('aadhaar', UploadedFile::fake()->image('aadhaar.jpg'))
        ->set('pan', UploadedFile::fake()->image('pan.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $investor = Investor::query()->sole();

    expect($investor->pan_number)->toBe('ABCDE1234F')
        ->and($investor->aadhaar_number)->toBe('234567890123')
        ->and($investor->kycDocuments()->pluck('type')->map->value->sort()->values()->all())
        ->toBe(['aadhaar', 'pan']);
});

test('investor fields are required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::investors.form')
        ->call('save')
        ->assertHasErrors([
            'name',
            'address',
            'mobile',
            'pan_number',
            'aadhaar_number',
            'bank_name',
            'bank_account_number',
            'bank_ifsc',
        ]);
});

test('investor identity and bank fields must be valid', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::investors.form')
        ->set(validInvestorPayload())
        ->set('email', 'not-an-email')
        ->set('pan_number', 'ABCDE1234')
        ->set('aadhaar_number', '123456789012')
        ->set('bank_account_number', 'ABC123')
        ->set('bank_ifsc', 'HDFC1001234')
        ->call('save')
        ->assertHasErrors([
            'email',
            'pan_number',
            'aadhaar_number',
            'bank_account_number',
            'bank_ifsc',
        ]);
});

test('investor mobile, pan and aadhaar must be unique', function () {
    $this->actingAs(User::factory()->create());

    Investor::factory()->create([
        'mobile' => '9876543210',
        'pan_number' => 'ABCDE1234F',
        'aadhaar_number' => '234567890123',
    ]);

    Livewire::test('pages::investors.form')
        ->set(validInvestorPayload())
        ->call('save')
        ->assertHasErrors(['mobile', 'pan_number', 'aadhaar_number']);
});

test('the investor form no longer offers an electricity bill upload', function () {
    Livewire::test('pages::investors.form')
        ->assertDontSee('Electricity bill');
});
