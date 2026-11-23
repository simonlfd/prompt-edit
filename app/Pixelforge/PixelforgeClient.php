<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeoutSeconds = 15,
    ) {}

    /**
     * POST /v1/generations. Returns the generation object (status 202, possibly an idempotent replay).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createGeneration(array $params, string $idempotencyKey): array
    {
        return $this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('generations', $params));
    }

    /** @return array<string, mixed> */
    public function getGeneration(string $providerId): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('generations/'.rawurlencode($providerId)));
    }

    /** GET /v1/pricing (no auth needed). @return array<string, mixed> */
    public function pricing(): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('pricing'));
    }

    /** GET /v1/usage for this key, per UTC day. @return array<string, mixed> */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(callable $request): array
    {
        $http = Http::baseUrl(rtrim($this->baseUrl, '/').'/')
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->connectTimeout(5);

        if ($this->apiKey) {
            $http = $http->withToken($this->apiKey);
        }

        try {
            $response = $request($http);
        } catch (ConnectionException $e) {
            throw new PixelforgeException(0, 'connection_error', 'Could not reach Pixelforge: '.$e->getMessage(), previous: $e);
        }

        if ($response->successful()) {
            return $response->json();
        }

        $retryAfter = $response->header('Retry-After');

        throw new PixelforgeException(
            status: $response->status(),
            errorCode: (string) $response->json('error.code', 'http_'.$response->status()),
            message: (string) $response->json('error.message', 'Pixelforge returned HTTP '.$response->status()),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }
}
