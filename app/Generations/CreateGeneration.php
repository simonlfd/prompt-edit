<?php

namespace App\Generations;

use App\Billing\CreditLedger;
use App\Billing\InsufficientCreditsException;
use App\Billing\PricingCalculator;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PricingRepository;
use Illuminate\Support\Facades\DB;

class CreateGeneration
{
    public function __construct(
        private readonly PricingRepository $pricing,
        private readonly PricingCalculator $calculator,
        private readonly CreditLedger $ledger,
    ) {}

    /**
     * Record the generation and reserve its worst-case cost, then submit it to Pixelforge in the background.
     *
     * @param  list<string>  $referenceImages
     *
     * @throws InsufficientCreditsException
     */
    public function handle(User $user, string $prompt, string $size, string $quality, array $referenceImages): Generation
    {
        $pricing = $this->pricing->current();
        $estimate = $this->calculator->estimate($pricing, $prompt, $size, $quality, count($referenceImages));

        $generation = DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $pricing, $estimate) {
            $generation = Generation::create([
                'user_id' => $user->id,
                'status' => GenerationStatus::Pending,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'rates' => $pricing->rates->toArray(), // Replaced by the rates Pixelforge fixes on the generation.
                'estimated_provider_nanos' => $estimate->expectedProviderNanos,
                'estimated_charge_nanos' => $estimate->expectedChargeNanos,
                'max_charge_nanos' => $estimate->maxChargeNanos,
            ]);

            $this->ledger->hold($generation);

            return $generation;
        });

        SubmitGeneration::dispatch($generation->id);

        return $generation;
    }
}
