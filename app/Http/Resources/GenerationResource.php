<?php

namespace App\Http\Resources;

use App\Billing\Money;
use App\Generations\ErrorMessages;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $waiting = $this->status === GenerationStatus::Pending && ErrorMessages::isWaitingForCapacity($this->error_code);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'waiting_for_capacity' => $waiting,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            'estimated_charge' => Money::toDecimal($this->estimated_charge_nanos),
            'max_charge' => Money::toDecimal($this->max_charge_nanos),
            'actual_charge' => $this->actual_charge_nanos === null ? null : Money::toDecimal($this->actual_charge_nanos),
            'usage' => $this->usage,
            'image_url' => $this->image_url,
            'image_url_expired' => $this->image_url_expires_at?->isPast() ?? false,
            'error' => $this->status === GenerationStatus::Failed || $waiting ? [
                'code' => $this->error_code,
                'message' => ErrorMessages::for($this->error_code),
            ] : null,
            'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
