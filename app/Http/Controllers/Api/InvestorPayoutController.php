<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPayoutRequest;
use App\Http\Requests\RejectPayoutRequest;
use App\Http\Resources\InvestorPayoutResource;
use App\Models\InvestorPayout;
use App\Services\InvestorPayoutService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InvestorPayoutController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InvestorPayout::class);

        $payouts = InvestorPayout::query()
            ->with(['investor', 'items.loan'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('month'), fn ($query) => $query->whereBetween('period_month', [
                CarbonImmutable::parse($request->string('month').'-01')->startOfMonth(),
                CarbonImmutable::parse($request->string('month').'-01')->endOfMonth(),
            ]))
            ->when($request->integer('investor_id'), fn ($query, $investorId) => $query->where('investor_id', $investorId))
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->paginate(25);

        return InvestorPayoutResource::collection($payouts);
    }

    public function generate(Request $request, InvestorPayoutService $service): JsonResponse
    {
        Gate::authorize('create', InvestorPayout::class);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $count = $service->generate(CarbonImmutable::parse($validated['month'].'-01'), $request->user());

        return response()->json(['generated' => $count, 'month' => $validated['month']]);
    }

    public function approve(Request $request, InvestorPayout $payout, InvestorPayoutService $service): InvestorPayoutResource
    {
        Gate::authorize('approve', $payout);

        return InvestorPayoutResource::make($service->approve($payout, $request->user())->load('investor'));
    }

    public function reject(RejectPayoutRequest $request, InvestorPayout $payout, InvestorPayoutService $service): InvestorPayoutResource
    {
        Gate::authorize('reject', $payout);

        return InvestorPayoutResource::make($service->reject($payout, $request->user(), $request->validated('reason'))->load('investor'));
    }

    public function process(ProcessPayoutRequest $request, InvestorPayout $payout, InvestorPayoutService $service): InvestorPayoutResource
    {
        Gate::authorize('process', $payout);

        $validated = $request->validated();

        return InvestorPayoutResource::make(
            $service->process($payout, $request->user(), $validated['payment_reference'], $validated['paid_at'], $validated['notes'] ?? null)->load('investor'),
        );
    }

    public function cancel(Request $request, InvestorPayout $payout, InvestorPayoutService $service): InvestorPayoutResource
    {
        Gate::authorize('cancel', $payout);

        return InvestorPayoutResource::make($service->cancel($payout, $request->user())->load('investor'));
    }
}
