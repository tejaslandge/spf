<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReversePaymentRequest;
use App\Http\Resources\InterestDistributionLedgerResource;
use App\Models\EmiPayment;
use App\Models\InvestorPayout;
use App\Services\EmiCollectionService;
use App\Services\InterestDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmiDistributionController extends Controller
{
    public function distribute(Request $request, EmiPayment $payment, InterestDistributionService $service): JsonResponse
    {
        Gate::authorize('create', InvestorPayout::class);

        $entries = $service->distributeForPayment($payment, $request->user());

        return response()->json([
            'payment_id' => $payment->id,
            'interest_component' => (string) $payment->interest_component,
            'entries' => InterestDistributionLedgerResource::collection($entries),
        ]);
    }

    public function reverse(ReversePaymentRequest $request, EmiPayment $payment, EmiCollectionService $service): JsonResponse
    {
        Gate::authorize('create', InvestorPayout::class);

        $reversed = $service->reversePayment($payment, $request->user(), $request->validated('reason'));

        return response()->json([
            'payment_id' => $reversed->id,
            'is_reversed' => (bool) $reversed->is_reversed,
            'reversed_at' => $reversed->reversed_at?->toIso8601String(),
        ]);
    }
}
