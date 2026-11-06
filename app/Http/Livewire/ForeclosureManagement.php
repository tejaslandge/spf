<?php

namespace App\Http\Livewire;

use App\Enums\LoanStatus;
use App\Models\ForeclosureRequest;
use App\Models\Loan;
use App\Services\ForeclosureService;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Support\Money;

#[Title('Foreclosure Management')]
class ForeclosureManagement extends Component
{
    public ForeclosureRequest $request;

    public Loan $loan;

    public bool $showRequestDetails = false;

    public string $rejectionReason = '';

    public bool $isSuperAdmin = false;

    protected ForeclosureService $foreclosureService;

    protected SettingsService $settingsService;

    public function mount(ForeclosureRequest $request): void
    {
        $this->request = $request;
        $this->loan = $request->loan;
        $this->isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function loadSettings(): void
    {
        $this->settingsService = app(SettingsService::class);
    }

    public function approve(): void
    {
        $this->authorize('approve', $this->request);

        $request = $this->foreclosureService->approveRequest($this->request, auth()->user());

        Flux::toast(variant: 'success', text: __('Foreclosure request approved. Loan marked as foreclosed.'));

        $this->redirectRoute('foreclosure-management', ['request' => $request->id], navigate: true);
    }

    public function reject(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3'],
        ]);

        $this->authorize('reject', $this->request);

        $request = $this->foreclosureService->rejectRequest($this->request, auth()->user(), $this->rejectionReason);

        Flux::toast(variant: 'danger', text: __('Foreclosure request rejected.'));

        $this->redirectRoute('foreclosure-management', ['request' => $request->id], navigate: true);
    }

    public function processPayment(): void
    {
        $this->authorize('process payment', $this->request);

        $request = $this->foreclosureService->processPayment($this->request, auth()->user());

        Flux::toast(variant: 'success', text: __('Foreclosure payment processed.'));

        $this->redirectRoute('foreclosure-management', ['request' => $request->id], navigate: true);
    }

    public function render()
    {
        // Load all foreclosure requests with their loans
        $this->loadSettings();

        $pendingRequests = ForeclosureRequest::pending()
            ->with(['loan.borrower', 'approvedBy'])
            ->latest('foreclosure_request_date')
            ->get();

        $approvedRequests = ForeclosureRequest::approved()
            ->with(['loan.borrower', 'approvedBy'])
            ->latest('actual_closure_date')
            ->take(10)
            ->get();

        $rejectedRequests = ForeclosureRequest::rejected()
            ->with(['loan.borrower', 'rejectionReason'])
            ->latest('updated_at')
            ->take(10)
            ->get();

        $paidRequests = ForeclosureRequest::paid()
            ->with(['loan.borrower'])
            ->latest('payment_date')
            ->take(10)
            ->get();

        return view('livewire.foreclosure-management', [
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests,
            'paidRequests' => $paidRequests,
            'isSuperAdmin' => $this->isSuperAdmin,
        ]);
    }
}