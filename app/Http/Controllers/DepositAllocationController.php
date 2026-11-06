<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Http\Requests\UpdateDepositStatusRequest;
use App\Models\DepositStatusHistory;
use App\Models\InvestorDeposit;
use App\Services\DepositAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositAllocationController extends Controller
{
    public function index(Request $request, DepositAllocationService $service): JsonResponse
    {
        $query = InvestorDeposit::query()->with('investor');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $term = '%'.strtolower((string) $request->query('search')).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(transaction_reference) LIKE ?', [$term])
                    ->orWhereHas('investor', function ($iq) use ($term) {
                        $iq->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(investor_code) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(mobile) LIKE ?', [$term]);
                    });
            });
        }

        $deposits = $query->latest('deposited_at')->latest('id')->paginate(20);

        $deposits->through(function (InvestorDeposit $deposit) use ($service) {
            $totals = $service->totalsFor($deposit);
            $deposit->setAttribute('allocated_amount', $totals['allocated_amount']);
            $deposit->setAttribute('available_amount', $totals['available_amount']);

            return $deposit;
        });

        return response()->json($deposits);
    }

    public function show(InvestorDeposit $deposit, DepositAllocationService $service): JsonResponse
    {
        $deposit->load('investor', 'clearedBy');
        $totals = $service->totalsFor($deposit);

        return response()->json([
            'deposit' => $deposit,
            'totals' => $totals,
            'history' => DepositStatusHistory::query()
                ->where('investor_deposit_id', $deposit->getKey())
                ->with('changedBy')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function update(UpdateDepositStatusRequest $request, InvestorDeposit $deposit, DepositAllocationService $service): JsonResponse
    {
        $updated = $service->updateStatus(
            $deposit,
            DepositStatus::from((string) $request->validated('status')),
            $request->validated('rejection_reason') ?? null,
            $request->validated('notes') ?? null,
            $request->user(),
        );

        return response()->json(['deposit' => $updated]);
    }

    public function history(InvestorDeposit $deposit): JsonResponse
    {
        return response()->json([
            'history' => DepositStatusHistory::query()
                ->where('investor_deposit_id', $deposit->getKey())
                ->with('changedBy')
                ->latest()
                ->paginate(20),
        ]);
    }
}
