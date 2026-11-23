<?php

use App\Billing\Money;
use App\Generations\CreateGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

const PF = 'https://pixelforge.test/v1';
const WEBHOOK_SECRET = 'whsec_test';

/** The 8-byte PNG signature plus some bytes: enough to pass as an image. */
const PNG = "\x89PNG\r\n\x1a\nfake-image-bytes";

/** The documented GET /v1/pricing response. */
function pricingResponse(): array
{
    return [
        'object' => 'pricing',
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}

function configurePixelforge(): void
{
    config([
        'services.pixelforge.base_url' => PF,
        'services.pixelforge.api_key' => 'pf_test',
        'services.pixelforge.webhook_secret' => WEBHOOK_SECRET,
    ]);
}

/** A Pixelforge generation object, like the API and webhooks return. */
function providerGeneration(Generation $generation, string $status, array $overrides = []): array
{
    $base = [
        'id' => $generation->provider_id ?? 'gen_'.strtoupper(substr($generation->id, -16)),
        'object' => 'generation',
        'status' => $status,
        'prompt' => $generation->prompt,
        'size' => $generation->size,
        'quality' => $generation->quality,
        'reference_images' => $generation->reference_images,
        'metadata' => ['generation_id' => $generation->id, 'user_id' => (string) $generation->user_id],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => pricingResponse()['rates'],
        'error' => null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => in_array($status, ['processing', 'succeeded', 'failed']) ? '2026-11-04T14:03:13Z' : null,
        'completed_at' => in_array($status, ['succeeded', 'failed']) ? '2026-11-04T14:03:29Z' : null,
    ];

    if ($status === 'succeeded') {
        $base['image_url'] = 'https://pixelforge.test/images/x.png?signature=abc';
        $base['image_url_expires_at'] = '2026-11-05T14:03:29Z';
        $base['usage'] = [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ];
    }

    if ($status === 'failed') {
        $base['error'] = ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'];
    }

    return array_replace($base, $overrides);
}

function userWithCredits(int $dollars = 10): User
{
    return User::factory()->create(['balance_nanos' => $dollars * Money::NANOS_PER_DOLLAR]);
}

/** Create a generation the way the app does (estimate + hold), without submitting it. */
function pendingGeneration(?User $user = null, array $attributes = []): Generation
{
    configurePixelforge();
    Http::fake([PF.'/pricing' => Http::response(pricingResponse())]);
    Queue::fake();

    $generation = app(CreateGeneration::class)->handle(
        $user ?? userWithCredits(),
        $attributes['prompt'] ?? 'A lighthouse on a cliff at dawn, oil painting',
        $attributes['size'] ?? '1536x1024',
        $attributes['quality'] ?? 'high',
        $attributes['reference_images'] ?? ['https://example.com/style.png'],
    );

    return $generation->fresh();
}

function webhookEvent(Generation $generation, string $status, string $eventId, array $overrides = []): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => 'generation.'.$status,
        'created_at' => '2026-11-04T14:03:29Z',
        'data' => ['object' => providerGeneration($generation, $status, $overrides)],
    ];
}

/** POST a correctly signed webhook. */
function sendWebhook(TestCase $test, array $event, ?string $signature = null)
{
    $payload = json_encode($event);
    $header = $signature ?? (new WebhookSignature(WEBHOOK_SECRET, 300))->header($payload, time());

    return $test->call('POST', '/webhooks/pixelforge', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => $header,
        'HTTP_PIXELFORGE_EVENT_ID' => $event['id'],
        'HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => '1',
    ], $payload);
}
