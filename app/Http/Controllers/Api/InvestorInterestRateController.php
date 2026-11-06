<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvestorInterestRateRequest;
use App\Models\InterestConfiguration;
use App\Models\InvestorInterestRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvestorInterestRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('create', InterestConfiguration::class);

        $rates = InvestorInterestRate::query()
            ->with('investor')
            ->when($request->integer('investor_id'), fn ($query, $investorId) => $query->where('investor_id', $investorId))
            ->orderByDesc('effective_from')
            ->paginate(25);

        return response()->json($rates);
    }

    public function store(StoreInvestorInterestRateRequest $request): JsonResponse
    {
        Gate::authorize('create', InterestConfiguration::class);

        $validated = $request->validated();

        $rate = InvestorInterestRate::query()->updateOrCreate(
            [
                'investor_id' => $validated['investor_id'],
                'effective_from' => $validated['effective_from'],
            ],
            $validated + [
                'is_active' => $request->boolean('is_active', true),
                'created_by' => $request->user()->getKey(),
            ],
        );

        return response()->json($rate->load('investor'), 201);
    }

    public function destroy(InvestorInterestRate $rate): JsonResponse
    {
        Gate::authorize('create', InterestConfiguration::class);

        $rate->update(['is_active' => false, 'effective_to' => today()->toDateString()]);

        return response()->json(['message' => 'Investor rate deactivated.']);
    }
}
