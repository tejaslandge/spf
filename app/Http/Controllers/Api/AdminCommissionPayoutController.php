<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPayoutRequest;
use App\Http\Requests\RejectPayoutRequest;
use App\Http\Resources\AdminCommissionPayoutResource;
use App\Models\AdminCommissionPayout;
use App\Services\AdminCommissionPayoutService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminCommissionPayoutController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AdminCommissionPayout::class);

        $payouts = AdminCommissionPayout::query()
            ->with('user')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('month'), fn ($query) => $query->whereBetween('period_month', [
                CarbonImmutable::parse($request->string('month').'-01')->startOfMonth(),
                CarbonImmutable::parse($request->string('month').'-01')->endOfMonth(),
            ]))
            ->when($request->integer('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->paginate(25);

        return AdminCommissionPayoutResource::collection($payouts);
    }

    public function generate(Request $request, AdminCommissionPayoutService $service): JsonResponse
    {
        Gate::authorize('viewAny', AdminCommissionPayout::class);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $count = $service->generate(CarbonImmutable::parse($validated['month'].'-01'), $request->user());

        return response()->json(['generated' => $count, 'month' => $validated['month']]);
    }

    public function approve(Request $request, AdminCommissionPayout $payout, AdminCommissionPayoutService $service): AdminCommissionPayoutResource
    {
        Gate::authorize('approve', $payout);

        return AdminCommissionPayoutResource::make($service->approve($payout, $request->user())->load('user'));
    }

    public function reject(RejectPayoutRequest $request, AdminCommissionPayout $payout, AdminCommissionPayoutService $service): AdminCommissionPayoutResource
    {
        Gate::authorize('reject', $payout);

        return AdminCommissionPayoutResource::make($service->reject($payout, $request->user(), $request->validated('reason'))->load('user'));
    }

    public function process(ProcessPayoutRequest $request, AdminCommissionPayout $payout, AdminCommissionPayoutService $service): AdminCommissionPayoutResource
    {
        Gate::authorize('process', $payout);

        $validated = $request->validated();

        return AdminCommissionPayoutResource::make(
            $service->process($payout, $request->user(), $validated['payment_reference'], $validated['paid_at'])->load('user'),
        );
    }

    public function cancel(Request $request, AdminCommissionPayout $payout, AdminCommissionPayoutService $service): AdminCommissionPayoutResource
    {
        Gate::authorize('cancel', $payout);

        return AdminCommissionPayoutResource::make($service->cancel($payout, $request->user())->load('user'));
    }
}
