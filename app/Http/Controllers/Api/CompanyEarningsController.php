<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InterestConfiguration;
use App\Services\DistributionReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyEarningsController extends Controller
{
    public function index(Request $request, DistributionReportService $service): JsonResponse
    {
        Gate::authorize('viewAny', InterestConfiguration::class);

        $from = $request->filled('from') ? CarbonImmutable::parse($request->string('from')) : null;
        $to = $request->filled('to') ? CarbonImmutable::parse($request->string('to')) : null;

        return response()->json([
            'summary' => $service->summary(),
            'monthly' => $service->monthly(12),
            'loans' => $service->byLoan($from, $to),
        ]);
    }
}
