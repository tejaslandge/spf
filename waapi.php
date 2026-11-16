<?php

/**
 * Central WhatsApp OTP API configuration (server-side only).
 *
 * IMPORTANT:
 * - Keep credentials here; do not expose them to frontend JavaScript/HTML.
 * - This file supports a safe include-only mode: when included, it returns the
 *   configuration array and does not execute any public API handler.
 * - Set WAAPI_BEARER_TOKEN to your real token in the server environment/file.
 *   Do not commit real secrets.
 */
if (! defined('WAAPI_CONFIG_ONLY')) {
    define('WAAPI_CONFIG_ONLY', true);
}

$waapiConfig = [
    'endpoint' => 'https://ciacloud.in/waapi.php',
    'bearer_token' => getenv('WAAPI_BEARER_TOKEN') ?: 'YOUR_EXISTING_API_TOKEN',
    'timeout' => 30,
    'default_country_code' => '91',
];

// Safe include-only mode: return config without running request handlers.
if (basename(__FILE__) !== basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    return $waapiConfig;
}

// If executed directly, behave as a config-only endpoint (no public handler).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
}

return $waapiConfig;
