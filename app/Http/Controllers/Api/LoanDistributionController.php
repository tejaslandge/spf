<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InterestDistributionLedgerResource;
use App\Models\InterestDistributionLedger;
use App\Models\Loan;
use App\Services\InterestDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LoanDistributionController extends Controller
{
    public function show(Loan $loan, InterestDistributionService $service): JsonResponse
    {
        Gate::authorize('view', $loan);

        $config = $loan->distributionConfig ?? $service->ensureSnapshot($loan, request()->user());

        return response()->json([
            'loan' => [
                'id' => $loan->id,
                'loan_code' => $loan->loan_code,
                'principal_amount' => (string) $loan->principal_amount,
                'annual_interest_rate' => (string) $loan->annual_interest_rate,
                'status' => $loan->status?->value,
            ],
            'config' => [
                'locked_at' => $config->locked_at?->toIso8601String(),
                'borrower_monthly_rate' => (string) $config->borrower_monthly_rate,
                'investor_allocation_percent' => (string) $config->investor_allocation_percent,
                'admin_allocation_percent' => (string) $config->admin_allocation_percent,
                'company_allocation_percent' => (string) $config->company_allocation_percent,
                'rules_snapshot' => $config->rules_snapshot,
            ],
            'investors' => $loan->investorDistributions()->with('investor')->get()->map(fn ($row): array => [
                'investor_id' => $row->investor_id,
                'investor_name' => $row->investor?->name,
                'principal_amount' => (string) $row->principal_amount,
                'monthly_rate' => (string) $row->monthly_rate,
                'share_percent' => (string) $row->share_percent,
            ]),
            'admins' => $loan->adminAssignments()->with('user')->get()->map(fn ($row): array => [
                'user_id' => $row->user_id,
                'admin_name' => $row->user?->name,
                'commission_rate' => (string) $row->commission_rate,
                'is_primary' => (bool) $row->is_primary,
            ]),
        ]);
    }

    public function entries(Request $request, Loan $loan): AnonymousResourceCollection
    {
        Gate::authorize('view', $loan);

        $entries = InterestDistributionLedger::query()
            ->with(['investor', 'admin', 'payment', 'loan'])
            ->where('loan_id', $loan->getKey())
            ->when($request->filled('beneficiary_type'), fn ($query) => $query->where('beneficiary_type', $request->string('beneficiary_type')))
            ->orderByDesc('id')
            ->paginate(50);

        return InterestDistributionLedgerResource::collection($entries);
    }

    public function snapshot(Request $request, Loan $loan, InterestDistributionService $service): JsonResponse
    {
        Gate::authorize('update', $loan);

        $config = $service->ensureSnapshot($loan, $request->user());

        return response()->json([
            'loan_id' => $loan->id,
            'locked_at' => $config->locked_at?->toIso8601String(),
            'rules_snapshot' => $config->rules_snapshot,
        ]);
    }
}
