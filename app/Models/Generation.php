<?php

namespace App\Models;

use App\Billing\Rates;
use App\Billing\TokenUsage;
use App\Generations\GenerationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property list<string> $reference_images
 * @property array<string, string> $rates
 * @property array<string, int>|null $usage
 * @property int $estimated_provider_nanos
 * @property int $estimated_charge_nanos
 * @property int $max_charge_nanos
 * @property int|null $actual_provider_nanos
 * @property int|null $actual_charge_nanos
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class Generation extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_provider_nanos' => 'integer',
            'estimated_charge_nanos' => 'integer',
            'max_charge_nanos' => 'integer',
            'actual_provider_nanos' => 'integer',
            'actual_charge_nanos' => 'integer',
            'image_url_expires_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query */
    public function scopeInFlight(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::inFlight());
    }

    public function ratesObject(): Rates
    {
        return new Rates($this->rates);
    }

    public function tokenUsage(): ?TokenUsage
    {
        return $this->usage ? TokenUsage::fromProvider($this->usage) : null;
    }

    /**
     * The request body for POST /v1/generations.
     *
     * @return array<string, mixed>
     */
    public function toProviderRequest(): array
    {
        return array_filter([
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images ?: null,
            'metadata' => ['generation_id' => $this->id, 'user_id' => (string) $this->user_id],
        ]);
    }
}
