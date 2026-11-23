<?php

namespace App\Http\Controllers;

use App\Billing\PricingCalculator;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PricingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Live cost estimate for the generate form. Same calculator as the real charge, so they can't disagree. */
class EstimateController extends Controller
{
    public function __invoke(Request $request, PricingRepository $pricingRepository, PricingCalculator $calculator): JsonResponse
    {
        try {
            $pricing = $pricingRepository->current();
        } catch (PixelforgeException) {
            return response()->json(['error' => 'Pricing is unavailable right now.'], 503);
        }

        $data = $request->validate([
            'prompt' => ['nullable', 'string'],
            'size' => ['required', Rule::in($pricing->sizes())],
            'quality' => ['required', Rule::in($pricing->qualities())],
            'reference_images' => ['nullable', 'integer', 'min:0', 'max:4'],
        ]);

        $estimate = $calculator->estimate(
            $pricing,
            trim((string) ($data['prompt'] ?? '')),
            $data['size'],
            $data['quality'],
            (int) ($data['reference_images'] ?? 0),
        );

        return response()->json([
            ...$estimate->toArray(),
            'rates' => $pricing->rates->toArray(),
            'reasoning_range' => $pricing->reasoningTokens[$data['quality']],
            'text_token_variance' => $pricing->textTokenVariance,
        ]);
    }
}
