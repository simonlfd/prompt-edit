<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property string|null $provider_generation_id
 * @property string|null $generation_id
 * @property int|null $delivery_attempt
 * @property string $outcome
 * @property array<string, mixed> $payload
 */
class WebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
