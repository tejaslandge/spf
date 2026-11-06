<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings\SettingsRegistry;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Seed every scalar default that has not been customised yet. Existing
        // values are never overwritten and structured values keep flowing from
        // the registry defaults until a Super Admin saves them.
        foreach (SettingsRegistry::keys() as $key => $meta) {
            $default = $meta['default'];

            if ($default === null || is_array($default)) {
                continue;
            }

            Setting::query()->firstOrCreate(['key' => $key], [
                'value' => is_bool($default) ? ($default ? '1' : '0') : (string) $default,
                'type' => $meta['type'],
                'group' => $meta['group'],
                'description' => $meta['label'],
                'is_sensitive' => $meta['sensitive'] ?? false,
            ]);
        }
    }
}
