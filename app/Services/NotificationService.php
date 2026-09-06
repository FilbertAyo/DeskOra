<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendSms(string $phone, string $message): bool
    {
        $phone = $this->normalizeTzPhone($phone);
        if (! $phone) {
            Log::warning('Beem SMS: Invalid phone number', ['phone' => $phone]);

            return false;
        }

        $baseUrl = config('beem.base_url');
        $apiKey = config('beem.api_key');
        $secret = config('beem.secret_key');
        $sender = config('beem.sender_id', 'Adilisha Portal');

        if (! $apiKey || ! $secret) {
            Log::warning('Beem credentials missing');

            return false;
        }

        // Use secret key as-is (base64 encoded) - this is what works with Beem API
        // The secret key from Beem is provided as base64 and should be used directly
        $auth = base64_encode($apiKey.':'.$secret);
        $endpoint = rtrim($baseUrl, '/').'/send';

        try {
            Log::info('Beem SMS: Sending SMS', [
                'phone' => $phone,
                'sender' => $sender,
                'endpoint' => $endpoint,
            ]);

            $resp = Http::timeout(30)->withHeaders([
                'Authorization' => 'Basic '.$auth,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'source_addr' => $sender,
                'encoding' => 0,
                'schedule_time' => '',
                'message' => $message,
                'recipients' => [
                    ['recipient_id' => 1, 'dest_addr' => $phone],
                ],
            ]);

            if ($resp->successful()) {
                Log::info('Beem SMS: Success', [
                    'phone' => $phone,
                    'response' => $resp->json(),
                ]);

                return true;
            }

            Log::error('Beem SMS failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'json' => $resp->json(),
                'phone' => $phone,
                'sender' => $sender,
            ]);
        } catch (\Throwable $e) {
            Log::error('Beem SMS exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone,
            ]);
        }

        return false;
    }

    private function normalizeTzPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }
        if (str_starts_with($digits, '255') && (strlen($digits) === 12)) {
            return $digits;
        }
        if (str_starts_with($digits, '+255') && strlen($digits) === 13) {
            return substr($digits, 1);
        }

        return $digits ?: null;
    }

    /**
     * Get SMS balance from Beem Africa API
     */
    public function getSmsBalance(): ?array
    {
        $apiKey = config('beem.api_key');
        $secret = config('beem.secret_key');

        if (! $apiKey || ! $secret) {
            Log::warning('Beem credentials missing for balance check');

            return null;
        }

        $auth = base64_encode($apiKey.':'.$secret);
        $endpoint = 'https://apisms.beem.africa/public/v1/vendors/balance';

        try {
            $resp = Http::timeout(30)->withHeaders([
                'Authorization' => 'Basic '.$auth,
                'Content-Type' => 'application/json',
            ])->get($endpoint);

            if ($resp->successful()) {
                $data = $resp->json();
                // Beem API returns: {"data": {"credit_balance": 100}}
                $balance = $data['data']['credit_balance'] ?? null;

                if ($balance === null) {
                    Log::warning('Beem SMS balance: credit_balance not found in response', [
                        'response' => $data,
                    ]);

                    return null;
                }

                return [
                    'balance' => $balance,
                    'currency' => 'TZS', // Beem API doesn't return currency in balance response
                    'success' => true,
                ];
            }

            Log::error('Beem SMS balance check failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'json' => $resp->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Beem SMS balance exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return null;
    }

    /**
     * Get SMS account information
     */
    public function getSmsAccountInfo(): ?array
    {
        $balanceData = $this->getSmsBalance();

        if (! $balanceData) {
            return null;
        }

        return [
            'balance' => $balanceData['balance'] ?? 0,
            'currency' => $balanceData['currency'] ?? 'TZS',
            'sender_id' => config('beem.sender_id', 'Adilisha Portal'),
            'api_key' => substr(config('beem.api_key', ''), 0, 8).'...', // Masked for security
            'status' => $balanceData['success'] ?? false,
        ];
    }
}
