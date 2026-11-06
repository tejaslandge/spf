<?php

return [

    'currency' => [
        'code' => env('APP_CURRENCY', 'INR'),
        'symbol' => env('APP_CURRENCY_SYMBOL', '₹'),
        'locale' => env('APP_CURRENCY_LOCALE', 'en_IN'),
    ],

    'money' => [
        'scale' => 2,
    ],

    'loan' => [
        'default_interest_method' => env('LOAN_DEFAULT_INTEREST_METHOD', 'reducing'),
        'foreclosure_fee_type' => env('LOAN_FORECLOSURE_FEE_TYPE', 'none'),
        'foreclosure_fee_value' => env('LOAN_FORECLOSURE_FEE_VALUE', 0),
        'penalty_per_day' => env('LOAN_PENALTY_PER_DAY', 0),
    ],

    'investor' => [
        'management_fee_percent' => env('INVESTOR_MANAGEMENT_FEE_PERCENT', 10),
        'tds_percent' => env('INVESTOR_TDS_PERCENT', 10),
    ],

    'notifications' => [
        'whatsapp' => [
            'enabled' => (bool) env('WHATSAPP_ENABLED', false),
            'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'template' => env('WHATSAPP_TEMPLATE', 'emi_reminder'),
        ],
        'sms' => [
            'enabled' => (bool) env('TWILIO_ENABLED', false),
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

];
