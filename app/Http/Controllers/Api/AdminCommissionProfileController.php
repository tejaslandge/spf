<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminCommissionProfileRequest;
use App\Models\AdminCommissionProfile;
use App\Models\InterestConfiguration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AdminCommissionProfileController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('create', InterestConfiguration::class);

        $profiles = AdminCommissionProfile::query()
            ->with('user')
            ->orderBy('designation')
            ->paginate(25);

        $admins = User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json([
            'data' => $profiles->items(),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
            'admins' => $admins,
        ]);
    }

    public function store(StoreAdminCommissionProfileRequest $request): JsonResponse
    {
        Gate::authorize('create', InterestConfiguration::class);

        $validated = $request->validated();

        $profile = AdminCommissionProfile::query()->updateOrCreate(
            ['user_id' => $validated['user_id']],
            $validated + [
                'is_active' => $request->boolean('is_active', true),
                'created_by' => $request->user()->getKey(),
            ],
        );

        return response()->json($profile->load('user'), 201);
    }

    public function destroy(AdminCommissionProfile $profile): JsonResponse
    {
        Gate::authorize('create', InterestConfiguration::class);

        $profile->update(['is_active' => false]);

        return response()->json(['message' => 'Commission profile deactivated.']);
    }
}
