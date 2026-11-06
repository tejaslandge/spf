<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInterestConfigurationRequest;
use App\Http\Resources\InterestConfigurationResource;
use App\Models\InterestConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InterestConfigurationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InterestConfiguration::class);

        $configurations = InterestConfiguration::query()
            ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate(25);

        return InterestConfigurationResource::collection($configurations);
    }

    public function store(StoreInterestConfigurationRequest $request): JsonResponse
    {
        $configuration = DB::transaction(function () use ($request): InterestConfiguration {
            if ($request->boolean('is_active', true)) {
                InterestConfiguration::query()->where('is_active', true)->update(['is_active' => false]);
            }

            return InterestConfiguration::query()->create($request->validated() + [
                'is_active' => $request->boolean('is_active', true),
                'created_by' => $request->user()->getKey(),
            ]);
        });

        return InterestConfigurationResource::make($configuration)->response()->setStatusCode(201);
    }

    public function update(StoreInterestConfigurationRequest $request, InterestConfiguration $configuration): InterestConfigurationResource
    {
        Gate::authorize('update', $configuration);

        DB::transaction(function () use ($request, $configuration): void {
            if ($request->boolean('is_active')) {
                InterestConfiguration::query()
                    ->whereKeyNot($configuration->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $configuration->update($request->validated() + [
                'is_active' => $request->boolean('is_active', $configuration->is_active),
            ]);
        });

        return InterestConfigurationResource::make($configuration->refresh());
    }

    public function destroy(InterestConfiguration $configuration): JsonResponse
    {
        Gate::authorize('delete', $configuration);

        $configuration->update(['is_active' => false]);

        return response()->json(['message' => 'Interest configuration deactivated.']);
    }
}
