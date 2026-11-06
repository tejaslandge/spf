<?php

use App\Models\Borrower;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function validBorrowerPayload(): array
{
    return [
        'full_name' => 'Sunita Deshmukh',
        'address' => 'Flat 4, Shivaji Nagar, Pune, Maharashtra 411005',
        'mobile' => '9123456780',
        'alternate_mobile' => '9123456781',
        'emergency_contact_name' => 'Amit Deshmukh',
        'emergency_contact_number' => '9123456782',
        'status' => 'active',
    ];
}

test('a borrower can be created with an electricity bill document', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::borrowers.form')
        ->set(validBorrowerPayload())
        ->set('electricity_bill', UploadedFile::fake()->image('bill.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $borrower = Borrower::query()->sole();

    expect($borrower->kycDocuments()->where('type', 'electricity_bill')->exists())->toBeTrue();
});

test('borrower fields are required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::borrowers.form')
        ->call('save')
        ->assertHasErrors([
            'full_name',
            'address',
            'mobile',
            'emergency_contact_name',
            'emergency_contact_number',
        ]);
});

test('the alternate mobile must differ from the primary mobile', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::borrowers.form')
        ->set(validBorrowerPayload())
        ->set('alternate_mobile', '9123456780')
        ->call('save')
        ->assertHasErrors(['alternate_mobile']);
});
