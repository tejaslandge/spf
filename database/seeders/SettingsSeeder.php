<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'company.name', 'value' => 'Finance & Capital', 'group' => 'company'],
            ['key' => 'company.address', 'value' => 'Mumbai, Maharashtra, India', 'group' => 'company'],
            ['key' => 'company.phone', 'value' => '+91 90000 00000', 'group' => 'company'],
            ['key' => 'loan.default_interest_rate', 'value' => '12', 'type' => 'string', 'group' => 'loan'],
            ['key' => 'loan.foreclosure_fee_type', 'value' => 'none', 'group' => 'loan'],
            ['key' => 'loan.foreclosure_fee_value', 'value' => '0', 'group' => 'loan'],
            ['key' => 'investor.management_fee_percent', 'value' => '10', 'group' => 'investor'],
            ['key' => 'investor.tds_percent', 'value' => '10', 'group' => 'investor'],
            ['key' => 'notifications.whatsapp_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'notifications'],
            ['key' => 'notifications.sms_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'notifications'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
