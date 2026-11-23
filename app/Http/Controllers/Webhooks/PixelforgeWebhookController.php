<?php

namespace App\Http\Controllers\Webhooks;

use App\Generations\HandleWebhookEvent;
use App\Pixelforge\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PixelforgeWebhookController
{
    public function __invoke(Request $request, WebhookSignature $signature, HandleWebhookEvent $handler): JsonResponse
    {
        // Verify against the raw body, before any JSON decoding.
        $payload = $request->getContent();

        if (! $signature->isValid($payload, $request->header('Pixelforge-Signature'))) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['id'], $event['type'])) {
            return response()->json(['error' => 'Malformed event.'], 400);
        }

        $attempt = $request->header('Pixelforge-Delivery-Attempt');
        $outcome = $handler->handle($event, is_numeric($attempt) ? (int) $attempt : null);

        // Always 2xx once recorded (including duplicates), so Pixelforge stops retrying.
        return response()->json(['received' => true, 'outcome' => $outcome]);
    }
}
