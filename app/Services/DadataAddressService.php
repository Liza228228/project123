<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DadataAddressService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function suggest(string $query, int $limit = 7): array
    {
        $payload = [
            'query' => $query,
            'count' => max(1, min($limit, 15)),
        ];

        $response = $this->request()
            ->withHeaders($this->authHeaders())
            ->acceptJson()
            ->post((string) config('services.dadata.suggestions_url'), $payload);

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('DaData suggestions request failed.', 0, $e);
        }

        $items = $response->json('suggestions', []);

        return is_array($items) ? $items : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function clean(string $address): array
    {
        $response = $this->request()
            ->withHeaders($this->authHeaders())
            ->acceptJson()
            ->post((string) config('services.dadata.cleaner_url'), [$address]);

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('DaData cleaner request failed.', 0, $e);
        }

        $items = $response->json();
        if (! is_array($items) || ! isset($items[0]) || ! is_array($items[0])) {
            return [];
        }

        return $items[0];
    }

    /**
     * @return array<string, mixed>
     */
    public function cleanName(string $fullName): array
    {
        $response = $this->request()
            ->withHeaders($this->authHeaders())
            ->acceptJson()
            ->post((string) config('services.dadata.cleaner_name_url'), [$fullName]);

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('DaData name cleaner request failed.', 0, $e);
        }

        $items = $response->json();
        if (! is_array($items) || ! isset($items[0]) || ! is_array($items[0])) {
            return [];
        }

        return $items[0];
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $apiKey = trim((string) config('services.dadata.api_key'));
        $secretKey = trim((string) config('services.dadata.secret_key'));

        if ($apiKey === '' || $secretKey === '') {
            throw new RuntimeException('DaData API keys are not configured.');
        }

        return [
            'Authorization' => 'Token '.$apiKey,
            'X-Secret' => $secretKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function timeoutSeconds(): float
    {
        $timeout = (float) config('services.dadata.timeout', 7);

        return $timeout > 0 ? $timeout : 7.0;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeoutSeconds())
            ->withOptions([
                'verify' => $this->verifySsl(),
            ]);
    }

    private function verifySsl(): bool
    {
        return (bool) config('services.dadata.verify_ssl', true);
    }
}
