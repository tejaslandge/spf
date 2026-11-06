<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Stream the authenticated user's profile photo from private storage.
     */
    public function avatar(): StreamedResponse
    {
        $user = request()->user();

        abort_if($user === null || $user->avatar_path === null, 404);
        abort_unless(Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response($user->avatar_path);
    }
}
