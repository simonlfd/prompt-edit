<?php

namespace App\Generations;

use App\Billing\CreditLedger;
use App\Billing\PricingCalculator;
use App\Billing\Rates;
use App\Billing\TokenUsage;
use App\Models\Generation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single place a generation's status changes in response to Pixelforge, whether the news came from the
 * create response, a webhook, or polling. It is safe to call with the same or older state any number of
 * times, in any order:
 *
 *  - The generation row is locked, so concurrent webhooks for the same generation are serialised.
 *  - Status only moves forward (pending → queued → processing → succeeded|failed); older news is ignored.
 *  - Money moves only on the transition into a final status, which can happen once. The ledger's
 *    unique index is a second line of defence.
 */
class ApplyProviderState
{
    public const APPLIED = 'applied';

    public const STALE = 'ignored_stale';

    public function __construct(
        private readonly PricingCalculator $calculator,
        private readonly CreditLedger $ledger,
    ) {}

    /** @param array<string, mixed> $object A Pixelforge generation object. */
    public function apply(string $generationId, array $object): string
    {
        return DB::transaction(function () use ($generationId, $object) {
            $generation = Generation::whereKey($generationId)->lockForUpdate()->firstOrFail();
            $incoming = GenerationStatus::from($object['status']);

            // Always record the provider id: a webhook can arrive before the create request returns.
            $generation->provider_id ??= $object['id'];

            if (! $this->movesForward($generation, $incoming)) {
                $generation->save();

                return self::STALE;
            }

            $generation->status = $incoming;
            $generation->rates = $object['rates'] ?? $generation->rates;
            $generation->started_at = $this->time($object['started_at'] ?? null) ?? $generation->started_at;
            $generation->next_attempt_at = null;

            if ($incoming !== GenerationStatus::Failed) {
                // Pixelforge accepted it, so any "waiting for capacity" error from earlier attempts is over.
                $generation->error_code = null;
                $generation->error_message = null;
            }

            match ($incoming) {
                GenerationStatus::Succeeded => $this->succeed($generation, $object),
                GenerationStatus::Failed => $this->fail($generation, $object['error']['code'] ?? 'generation_failed', $object['error']['message'] ?? null, $object),
                default => $generation->save(),
            };

            return self::APPLIED;
        });
    }

    /** Fail a generation Pixelforge never accepted (e.g. rejected prompt, or we gave up retrying). */
    public function failLocally(string $generationId, string $code, string $message): void
    {
        DB::transaction(function () use ($generationId, $code, $message) {
            $generation = Generation::whereKey($generationId)->lockForUpdate()->firstOrFail();

            if ($generation->status->isFinal()) {
                return;
            }

            $generation->status = GenerationStatus::Failed;
            $generation->next_attempt_at = null;
            $this->fail($generation, $code, $message, []);
        });
    }

    private function movesForward(Generation $generation, GenerationStatus $incoming): bool
    {
        if ($generation->status->isFinal()) {
            // Exception: we failed it locally without Pixelforge ever confirming it (we gave up after network
            // errors), but Pixelforge did create it after all. Pixelforge's word wins, so our records
            // match what it bills.
            return $generation->status === GenerationStatus::Failed
                && $generation->submitted_at === null
                && $incoming->isFinal();
        }

        return $incoming->rank() > $generation->status->rank();
    }

    /** @param array<string, mixed> $object */
    private function succeed(Generation $generation, array $object): void
    {
        $usage = TokenUsage::fromProvider($object['usage']);
        $providerNanos = $this->calculator->providerCost(new Rates($object['rates']), $usage);

        $generation->forceFill([
            'usage' => $object['usage'],
            'actual_provider_nanos' => $providerNanos,
            'actual_charge_nanos' => $this->calculator->chargeFor($providerNanos),
            'image_url' => $object['image_url'] ?? null,
            'image_url_expires_at' => $this->time($object['image_url_expires_at'] ?? null),
            'completed_at' => $this->time($object['completed_at'] ?? null) ?? now(),
            'error_code' => null,
            'error_message' => null,
        ])->save();

        $this->ledger->settle($generation);
    }

    /** @param array<string, mixed> $object */
    private function fail(Generation $generation, string $code, ?string $message, array $object): void
    {
        // Failed generations are never billed, by Pixelforge or by us.
        $generation->forceFill([
            'actual_provider_nanos' => 0,
            'actual_charge_nanos' => 0,
            'error_code' => $code,
            'error_message' => $message,
            'completed_at' => $this->time($object['completed_at'] ?? null) ?? now(),
        ])->save();

        $this->ledger->release($generation);
    }

    private function time(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->utc() : null;
    }
}
