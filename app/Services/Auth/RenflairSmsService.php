<?php

namespace App\Services\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin client for the Renflair SMS gateway.
 *
 * Credentials are read from config and never written to logs or responses.
 * The gateway is only used to deliver the one-time code; OTP verification
 * happens locally against the hashed value in the pending challenge.
 */
final class RenflairSmsService
{
    /**
     * Send a six-digit code to a 10-digit Indian mobile number.
     */
    public function sendOtp(string $nationalPhone, string $otp): RenflairSmsResult
    {
        $url = trim((string) config('services.renflair.url'));
        $apiKey = trim((string) config('services.renflair.api_key'));

        if ($url === '' || $apiKey === '') {
            return RenflairSmsResult::failure(RenflairSmsResult::NOT_CONFIGURED);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(max(1, (int) config('services.renflair.timeout', 10)))
                ->get($url, [
                    'API' => $apiKey,
                    'PHONE' => $nationalPhone,
                    'OTP' => $otp,
                ]);
        } catch (ConnectionException) {
            // Never log the request URL: it contains the API key.
            Log::warning('Renflair SMS request could not connect.');

            return RenflairSmsResult::failure(RenflairSmsResult::UNAVAILABLE);
        } catch (Throwable $exception) {
            Log::warning('Renflair SMS request failed.', [
                'exception' => $exception::class,
            ]);

            return RenflairSmsResult::failure(RenflairSmsResult::UNAVAILABLE);
        }

        if ($response->serverError()) {
            Log::warning('Renflair SMS server error.', [
                'status' => $response->status(),
            ]);

            return RenflairSmsResult::failure(RenflairSmsResult::UNAVAILABLE);
        }

        if (! $response->successful()) {
            return RenflairSmsResult::failure(RenflairSmsResult::REJECTED);
        }

        if ($this->payloadIndicatesFailure($response)) {
            return RenflairSmsResult::failure(RenflairSmsResult::REJECTED);
        }

        // A 2xx response without an explicit failure flag is treated as sent.
        return RenflairSmsResult::success();
    }

    private function payloadIndicatesFailure(Response $response): bool
    {
        $data = $response->json();

        if (! is_array($data)) {
            return $this->plainTextIndicatesFailure($response->body());
        }

        $nested = is_array($data['data'] ?? null) ? $data['data'] : [];

        foreach ([$data, $nested] as $payload) {
            $success = $payload['success'] ?? null;

            if ($success === false || $success === 0 || $success === 'false' || $success === '0') {
                return true;
            }

            $status = $payload['status'] ?? null;

            if ($status === false || $status === 0) {
                return true;
            }

            if (is_string($status) && in_array(
                strtolower(trim($status)),
                ['error', 'failed', 'failure', 'invalid', 'rejected', 'denied'],
                true,
            )) {
                return true;
            }

            $error = $payload['error'] ?? null;

            if (is_string($error) && trim($error) !== '') {
                return true;
            }
        }

        return false;
    }

    private function plainTextIndicatesFailure(string $body): bool
    {
        $body = trim($body);

        if ($body === '') {
            return false;
        }

        return preg_match('/\b(invalid|error|failed|failure|denied|unauthori[sz]ed)\b/i', $body) === 1;
    }
}
