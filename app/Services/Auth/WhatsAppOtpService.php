<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Log;

/**
 * Backend cURL client for the existing WhatsApp OTP API (ciacloud.in/waapi.php).
 *
 * Uses the exact endpoint/request format required by the integration and keeps
 * credentials server-side via waapi.php configuration.
 */
final class WhatsAppOtpService
{
    /**
     * @return array{endpoint: string, bearer_token: string, timeout: int, default_country_code: string}
     */
    public function config(): array
    {
        $config = require base_path('waapi.php');

        return [
            'endpoint' => (string) ($config['endpoint'] ?? 'https://ciacloud.in/waapi.php'),
            'bearer_token' => (string) ($config['bearer_token'] ?? ''),
            'timeout' => max(1, (int) ($config['timeout'] ?? 30)),
            'default_country_code' => (string) ($config['default_country_code'] ?? '91'),
        ];
    }

    /**
     * Send a generated OTP to the exact dynamic phone number entered by the user.
     */
    public function sendOtp(string $phone, string $otp): WhatsAppOtpResult
    {
        $config = $this->config();
        $token = trim($config['bearer_token']);

        if ($token === '' || $token === 'YOUR_EXISTING_API_TOKEN') {
            return WhatsAppOtpResult::failure(WhatsAppOtpResult::NOT_CONFIGURED, 0, null, 'WhatsApp API token is not configured.');
        }

        $payload = json_encode([
            'phone' => $phone,
            'otp' => $otp,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return WhatsAppOtpResult::failure(WhatsAppOtpResult::REJECTED, 0, null, 'Unable to encode OTP request.');
        }

        $ch = curl_init($config['endpoint']);

        if ($ch === false) {
            return WhatsAppOtpResult::failure(WhatsAppOtpResult::UNAVAILABLE, 0, null, 'Unable to initialise cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $config['timeout'],
            CURLOPT_CONNECTTIMEOUT => $config['timeout'],
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (is_resource($ch) || $ch instanceof \CurlHandle) {
            curl_close($ch);
        }

        if ($errno !== 0 || $body === false) {
            Log::warning('WhatsApp OTP API request failed.', [
                'status' => $status,
                'curl_errno' => $errno,
            ]);

            return WhatsAppOtpResult::failure(WhatsAppOtpResult::UNAVAILABLE, $status, null, $error !== '' ? $error : 'Unable to reach WhatsApp OTP API.');
        }

        $decoded = json_decode((string) $body, true);
        $response = is_array($decoded) ? $decoded : null;
        $message = $this->extractMessage($response, (string) $body);

        if ($status === 403) {
            return WhatsAppOtpResult::failure(
                WhatsAppOtpResult::UNAUTHORIZED,
                $status,
                $response,
                $message ?? 'This number is not authorized.'
            );
        }

        if ($status >= 200 && $status < 300 && ! $this->payloadIndicatesFailure($response, (string) $body)) {
            return WhatsAppOtpResult::success($status, $response, $message);
        }

        if ($status >= 500) {
            return WhatsAppOtpResult::failure(WhatsAppOtpResult::UNAVAILABLE, $status, $response, $message);
        }

        return WhatsAppOtpResult::failure(WhatsAppOtpResult::REJECTED, $status, $response, $message);
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function extractMessage(?array $response, string $body): ?string
    {
        if (is_array($response)) {
            foreach (['message', 'error', 'detail', 'msg'] as $key) {
                $value = $response[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }

            $nested = $response['data'] ?? null;
            if (is_array($nested)) {
                foreach (['message', 'error', 'detail', 'msg'] as $key) {
                    $value = $nested[$key] ?? null;
                    if (is_string($value) && trim($value) !== '') {
                        return trim($value);
                    }
                }
            }
        }

        $body = trim($body);

        return $body !== '' && mb_strlen($body) <= 200 ? $body : null;
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function payloadIndicatesFailure(?array $response, string $body): bool
    {
        if (! is_array($response)) {
            return preg_match('/\b(invalid|error|failed|failure|denied|unauthori[sz]ed|not authorized)\b/i', $body) === 1;
        }

        $success = $response['success'] ?? $response['status'] ?? null;

        if ($success === false || $success === 0 || $success === 'false' || $success === '0') {
            return true;
        }

        if (is_string($success) && in_array(strtolower(trim($success)), ['error', 'failed', 'failure', 'invalid', 'rejected', 'denied'], true)) {
            return true;
        }

        return false;
    }
}
