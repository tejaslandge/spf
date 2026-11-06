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

    public static function logoPath(): ?string
    {
        try {
            $path = Setting::get('company.logo');
        } catch (Throwable) {
            return null;
        }

        return is_string($path) && $path !== '' ? $path : null;
    }

    public static function logoUrl(): ?string
    {
        return self::urlFor(self::logoPath());
    }

    /**
     * Resolve a path on the public disk to a same-origin URL.
     *
     * The public disk URL is built from APP_URL, which is often wrong behind a
     * proxy, tunnel or local host alias (for example a Herd site). Returning
     * the path portion keeps the image served from the current origin instead
     * of a dead localhost host, and keeps working for remote disks.
     */
    public static function urlFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            $url = Storage::disk('public')->url($path);
        } catch (Throwable) {
            return null;
        }

        $urlPath = parse_url($url, PHP_URL_PATH);

        if (is_string($urlPath) && $urlPath !== '') {
            $query = parse_url($url, PHP_URL_QUERY);

            return is_string($query) && $query !== '' ? $urlPath.'?'.$query : $urlPath;
        }

        return $url;
    }
}
