<?php

namespace App\Pixelforge;

/**
 * Pixelforge-Signature: t=<unix timestamp>,v1=<hex HMAC-SHA256 of "<timestamp>.<raw body>">.
 */
final class WebhookSignature
{
    public function __construct(private readonly string $secret, private readonly int $toleranceSeconds) {}

    public function isValid(string $payload, ?string $header, ?int $now = null): bool
    {
        if ($this->secret === '' || ! $header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = (int) ($parts['t'] ?? 0);
        $signature = $parts['v1'] ?? '';

        if ($timestamp <= 0 || abs(($now ?? time()) - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        return hash_equals($this->sign($payload, $timestamp), $signature);
    }

    public function sign(string $payload, int $timestamp): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);
    }

    public function header(string $payload, int $timestamp): string
    {
        return "t={$timestamp},v1=".$this->sign($payload, $timestamp);
    }
}
