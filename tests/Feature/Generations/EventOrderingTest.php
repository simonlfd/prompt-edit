<?php

use App\Generations\ApplyProviderState;
use App\Generations\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Tests\TestCase;

/**
 * Pixelforge delivers events at least once and in any order. Whatever order the deliveries for one
 * generation arrive in, including duplicates, the end state and the money must be identical.
 */

/** @return list<list<int>> every ordering of the indexes 0..n-1 */
function permutations(int $n): array
{
    if ($n === 1) {
        return [[0]];
    }

    $result = [];
    foreach (permutations($n - 1) as $perm) {
        for ($i = 0; $i < $n; $i++) {
            $result[] = [...array_slice($perm, 0, $i), $n - 1, ...array_slice($perm, $i)];
        }
    }

    return $result;
}

/**
 * Deliveries: processing, its duplicate, the final event, its duplicate, plus the create response
 * (which can land before or after any webhook). 5! = 120 orderings.
 *
 * @return list<array{string, string}> [kind, status]
 */
function deliveries(string $final): array
{
    return [
        ['webhook:evt_p', 'processing'],
        ['webhook:evt_p', 'processing'],
        ['webhook:evt_f', $final],
        ['webhook:evt_f', $final],
        ['create-response', 'queued'],
    ];
}

function deliver(TestCase $test, Generation $generation, array $delivery, string $final): void
{
    [$kind, $status] = $delivery;

    if ($kind === 'create-response') {
        app(ApplyProviderState::class)->apply($generation->id, providerGeneration($generation, $status, ['id' => 'gen_X']));

        return;
    }

    sendWebhook($test, webhookEvent($generation, $status, substr($kind, 8), ['id' => 'gen_X']))->assertOk();
}

dataset('orderings', fn () => array_map(fn ($p) => [implode(',', $p)], permutations(5)));

it('reaches the same succeeded state and charges exactly once in any order', function (string $order) {
    $generation = pendingGeneration();
    $startBalance = $generation->user->fresh()->balance_nanos + $generation->max_charge_nanos; // before the hold

    $rank = 0;
    foreach (explode(',', $order) as $i) {
        deliver($this, $generation, deliveries('succeeded')[(int) $i], 'succeeded');

        // Never backwards, not even for a moment (e.g. a late create response after "processing").
        $now = $generation->fresh()->status->rank();
        expect($now)->toBeGreaterThanOrEqual($rank);
        $rank = $now;
    }

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_id)->toBe('gen_X')
        ->and($generation->actual_charge_nanos)->toBe(381_887_500)
        ->and(CreditTransaction::where('generation_id', $generation->id)->where('type', 'charge')->count())->toBe(1)
        ->and($generation->user->fresh()->balance_nanos)->toBe($startBalance - 381_887_500)
        ->and(WebhookEvent::count())->toBe(2)
        ->and((int) WebhookEvent::sum('duplicate_deliveries'))->toBe(2);
})->with('orderings');

it('reaches the same free failed state in any order', function (string $order) {
    $generation = pendingGeneration();
    $startBalance = $generation->user->fresh()->balance_nanos + $generation->max_charge_nanos;

    $rank = 0;
    foreach (explode(',', $order) as $i) {
        deliver($this, $generation, deliveries('failed')[(int) $i], 'failed');

        $now = $generation->fresh()->status->rank();
        expect($now)->toBeGreaterThanOrEqual($rank);
        $rank = $now;
    }

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->actual_charge_nanos)->toBe(0)
        ->and(CreditTransaction::where('generation_id', $generation->id)->where('type', 'charge')->count())->toBe(0)
        ->and(CreditTransaction::where('generation_id', $generation->id)->where('type', 'release')->count())->toBe(1)
        ->and($generation->user->fresh()->balance_nanos)->toBe($startBalance);
})->with('orderings');
