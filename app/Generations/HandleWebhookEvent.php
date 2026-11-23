<?php

namespace App\Generations;

use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records a verified Pixelforge event exactly once and applies it.
 *
 * The event row and the state change commit in one transaction: if applying fails, the event row rolls
 * back too, we answer 500, and Pixelforge's retry gets a clean second chance. A duplicate delivery finds
 * the event id already taken and does nothing.
 */
class HandleWebhookEvent
{
    public const DUPLICATE = 'duplicate';

    public function __construct(private readonly ApplyProviderState $state) {}

    /** @param array<string, mixed> $event */
    public function handle(array $event, ?int $deliveryAttempt): string
    {
        return DB::transaction(function () use ($event, $deliveryAttempt) {
            $object = $event['data']['object'] ?? null;
            $isGeneration = is_array($object) && ($object['object'] ?? null) === 'generation';

            $inserted = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'provider_generation_id' => $isGeneration ? $object['id'] : null,
                'delivery_attempt' => $deliveryAttempt,
                'outcome' => 'received',
                'payload' => json_encode($event),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return self::DUPLICATE;
            }

            $generation = $isGeneration ? $this->findGeneration($object) : null;

            $outcome = match (true) {
                $event['type'] === 'ping' => 'ping',
                ! $isGeneration => 'ignored_unknown_type',
                $generation === null => 'unmatched',
                default => $this->state->apply($generation->id, $object),
            };

            if ($outcome === 'unmatched') {
                Log::warning('Pixelforge webhook for an unknown generation.', ['event' => $event['id'], 'generation' => $object['id'] ?? null]);
            }

            WebhookEvent::where('event_id', $event['id'])->update([
                'outcome' => $outcome,
                'generation_id' => $generation?->id,
            ]);

            return $outcome;
        });
    }

    /**
     * Match on our own id from metadata first: the webhook can arrive before the create request returns,
     * when we don't know Pixelforge's id yet.
     *
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $ourId = $object['metadata']['generation_id'] ?? null;

        return ($ourId ? Generation::whereKey($ourId)->first() : null)
            ?? Generation::where('provider_id', $object['id'])->first();
    }
}
