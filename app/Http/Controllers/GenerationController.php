<?php

namespace App\Http\Controllers;

use App\Billing\InsufficientCreditsException;
use App\Billing\Money;
use App\Generations\CreateGeneration;
use App\Http\Requests\GenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PricingRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request, PricingRepository $pricingRepository): Response
    {
        try {
            $pricing = $pricingRepository->current();
        } catch (PixelforgeException) {
            $pricing = null;
        }

        return Inertia::render('Studio', [
            'generations' => GenerationResource::collection(
                $request->user()->generations()->latest()->limit(60)->get()
            ),
            'options' => [
                'sizes' => $pricing?->sizes() ?? [],
                'qualities' => $pricing?->qualities() ?? [],
                'max_prompt_length' => GenerationRequest::MAX_PROMPT_LENGTH,
                'max_reference_images' => GenerationRequest::MAX_REFERENCE_IMAGES,
                'markup_percent' => (int) config('billing.markup_percent'),
            ],
            'credit_packs' => config('billing.credit_packs'),
        ]);
    }

    public function store(GenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        try {
            $createGeneration->handle(
                $request->user(),
                $request->validated('prompt'),
                $request->validated('size'),
                $request->validated('quality'),
                $request->referenceImages(),
            );
        } catch (InsufficientCreditsException $e) {
            return back()->withErrors([
                'credits' => sprintf(
                    'This could cost up to %s and you have %s. Buy credits to continue.',
                    Money::format($e->requiredNanos),
                    Money::format($e->balanceNanos),
                ),
            ]);
        } catch (PixelforgeException) {
            return back()->withErrors(['prompt' => 'We can’t reach Pixelforge’s price list right now, so we can’t price this. Try again in a minute.']);
        }

        return back()->with('success', 'Generation started. You’ll see it below as it progresses.');
    }
}
