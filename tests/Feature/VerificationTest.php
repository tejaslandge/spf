<?php

use App\Enums\DepositStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Enums\UserRole;
use App\Models\Borrower;
use App\Models\DepositStatusHistory;
use App\Models\Investor;
use App\Models\KycDocument;
use App\Models\KycVerificationHistory;
use App\Models\User;
use App\Services\DepositAllocationService;
use App\Services\KycVerificationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function superAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin]);
}

function adminUser(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function makeBorrowerWithDocs(User $actor, array $types = ['aadhaar', 'pan', 'electricity_bill'], string $status = 'pending'): Borrower
{
    Storage::fake('local');
    $borrower = Borrower::factory()->create();

    foreach ($types as $type) {
        $borrower->kycDocuments()->create([
            'type' => KycDocumentType::from($type),
            'file_path' => 'kyc/borrowers/'.$borrower->id.'/'.$type.'.jpg',
            'original_name' => $type.'.jpg',
            'mime_type' => 'image/jpeg',
            'status' => $status,
            'number' => '123456789012',
        ]);
        Storage::disk('local')->put('kyc/borrowers/'.$borrower->id.'/'.$type.'.jpg', 'fake');
    }

    return $borrower->refresh();
}

function makeInvestorWithDocs(array $types = ['aadhaar', 'pan'], string $status = 'pending'): Investor
{
    Storage::fake('local');
    $investor = Investor::factory()->create();

    foreach ($types as $type) {
        $investor->kycDocuments()->create([
            'type' => KycDocumentType::from($type),
            'file_path' => 'kyc/investors/'.$investor->id.'/'.$type.'.jpg',
            'original_name' => $type.'.jpg',
            'mime_type' => 'image/jpeg',
            'status' => $status,
            'number' => '123456789012',
        ]);
        Storage::disk('local')->put('kyc/investors/'.$investor->id.'/'.$type.'.jpg', 'fake');
    }

    return $investor->refresh();
}

// --- Module 1: Document Verification ---

test('borrower with all pending documents is pending', function () {
    $borrower = makeBorrowerWithDocs(superAdmin());

    expect(KycDocument::overallStatusFor(Borrower::class, $borrower->id))->toBe(KycStatus::Pending);
});

test('investor with all pending documents is pending', function () {
    $investor = makeInvestorWithDocs();

    expect(KycDocument::overallStatusFor(Investor::class, $investor->id))->toBe(KycStatus::Pending);
});

test('one mandatory document rejected makes overall rejected', function () {
    $actor = superAdmin();
    $borrower = makeBorrowerWithDocs($actor);
    $svc = app(KycVerificationService::class);
    $doc = $borrower->kycDocuments()->where('type', 'aadhaar')->first();

    $svc->setDocumentStatus($doc, KycStatus::Rejected, 'Blurry image', $actor);

    expect(KycDocument::overallStatusFor(Borrower::class, $borrower->id))->toBe(KycStatus::Rejected);
    expect($doc->refresh()->notes)->toContain('Blurry');
    expect(KycVerificationHistory::query()->where('rejection_reason', 'Blurry image')->exists())->toBeTrue();
});

test('all mandatory documents verified makes overall verified and allows approval', function () {
    $actor = superAdmin();
    $borrower = makeBorrowerWithDocs($actor);
    $svc = app(KycVerificationService::class);

    $borrower->kycDocuments()->each(fn ($d) => $svc->setDocumentStatus($d, KycStatus::Verified, null, $actor));

    expect(KycDocument::overallStatusFor(Borrower::class, $borrower->id))->toBe(KycStatus::Verified);

    $result = $svc->approveSubject(Borrower::class, $borrower->id, $actor);
    expect($result['verified'])->toBeTrue();
});

test('replacement document requires re-verification and history is retained', function () {
    $actor = superAdmin();
    $borrower = makeBorrowerWithDocs($actor);
    $svc = app(KycVerificationService::class);

    $old = $borrower->kycDocuments()->where('type', 'aadhaar')->first();
    $svc->setDocumentStatus($old, KycStatus::Rejected, 'Old scan', $actor);

    // Simulate replacement upload (new pending document, latest wins).
    $borrower->kycDocuments()->create([
        'type' => KycDocumentType::Aadhaar,
        'file_path' => 'kyc/borrowers/'.$borrower->id.'/aadhaar2.jpg',
        'original_name' => 'aadhaar2.jpg',
        'mime_type' => 'image/jpeg',
        'status' => 'pending',
    ]);

    // Latest aadhaar is pending, so overall is pending (not verified via the old rejected one).
    expect(KycDocument::overallStatusFor(Borrower::class, $borrower->id))->toBe(KycStatus::Pending);
    expect(KycVerificationHistory::query()->where('kyc_document_id', $old->id)->exists())->toBeTrue();
});

test('super admin cannot approve with pending documents', function () {
    $actor = superAdmin();
    $borrower = makeBorrowerWithDocs($actor);
    $svc = app(KycVerificationService::class);

    $this->actingAs($actor);

    expect(fn () => $svc->approveSubject(Borrower::class, $borrower->id, $actor))
        ->toThrow(InvalidArgumentException::class);
});

test('admin can access verification pages', function () {
    $admin = adminUser();
    $this->actingAs($admin);

    $this->get(route('super-admin.verification.borrowers'))->assertOk();
    $this->get(route('super-admin.verification.investors'))->assertOk();
});

test('rejection requires a reason for documents', function () {
    $actor = superAdmin();
    $borrower = makeBorrowerWithDocs($actor);
    $svc = app(KycVerificationService::class);
    $doc = $borrower->kycDocuments()->first();

    expect(fn () => $svc->setDocumentStatus($doc, KycStatus::Rejected, '', $actor))
        ->toThrow(ValidationException::class);
});

// --- Module 2: Deposit Allocation ---

test('pending deposit changed to cleared increases available capital', function () {
    $actor = superAdmin();
    $investor = Investor::factory()->create();
    $deposit = $investor->deposits()->create([
        'amount' => 100000,
        'status' => DepositStatus::Pending,
        'deposited_at' => now()->toDateString(),
        'created_by' => $actor->id,
    ]);
    $svc = app(DepositAllocationService::class);

    $svc->updateStatus($deposit, DepositStatus::Cleared, null, 'verified', $actor);

    expect($deposit->refresh()->status)->toBe(DepositStatus::Cleared);
    expect(DepositStatusHistory::query()->where('investor_deposit_id', $deposit->id)->where('new_status', 'cleared')->exists())->toBeTrue();
    expect($svc->totalsFor($deposit->refresh())['available_amount'])->toBe('100000.00');
});

test('pending deposit changed to rejected requires a reason', function () {
    $actor = superAdmin();
    $investor = Investor::factory()->create();
    $deposit = $investor->deposits()->create([
        'amount' => 50000,
        'status' => DepositStatus::Pending,
        'deposited_at' => now()->toDateString(),
        'created_by' => $actor->id,
    ]);
    $svc = app(DepositAllocationService::class);

    expect(fn () => $svc->updateStatus($deposit, DepositStatus::Rejected, '', null, $actor))
        ->toThrow(ValidationException::class);

    $svc->updateStatus($deposit, DepositStatus::Rejected, 'Duplicate payment', null, $actor);
    expect($deposit->refresh()->status)->toBe(DepositStatus::Rejected);
    expect($svc->totalsFor($deposit->refresh())['available_amount'])->toBe('0.00');
});

test('rejected deposit cannot be allocated', function () {
    $actor = superAdmin();
    $investor = Investor::factory()->create();
    $deposit = $investor->deposits()->create([
        'amount' => 100000,
        'status' => DepositStatus::Rejected,
        'deposited_at' => now()->toDateString(),
        'created_by' => $actor->id,
    ]);
    $svc = app(DepositAllocationService::class);

    expect(fn () => $svc->assertAllocatable('10000', $deposit->refresh()))
        ->toThrow(InvalidArgumentException::class);
});

test('admin can access deposit allocation page', function () {
    $this->actingAs(adminUser());

    $this->get(route('super-admin.deposit-allocation.index'))->assertOk();
});

test('unauthorized document preview request is blocked for guests', function () {
    $borrower = makeBorrowerWithDocs(superAdmin());
    $doc = $borrower->kycDocuments()->first();

    $this->get(route('documents.preview', $doc))->assertRedirect('/login');
});
