<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Application branding resolved from the stored company settings.
 */
final class Branding
{
    public static function name(): string
    {
        try {
            $name = Setting::get('company.name');
        } catch (Throwable) {
            $name = null;
        }

        return is_string($name) && $name !== '' ? $name : (string) config('app.name', 'Finance & Capital');
    }

    public static function logoUrl(): ?string
    {
        try {
            $path = Setting::get('company.logo');
        } catch (Throwable) {
            return null;
        }

        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
