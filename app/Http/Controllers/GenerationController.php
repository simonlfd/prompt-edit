<?php

namespace App\Http\Controllers;

use App\Billing\InsufficientCreditsException;
use App\Billing\Money;
use App\Generations\CreateGeneration;
use App\Generations\GenerationStatus;
use App\Http\Requests\GenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
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

        $user = $request->user();
        $reuse = $request->query('reuse');

        return Inertia::render('Studio', [
            'generations' => GenerationResource::collection($user->generations()->latest()->limit(20)->get()),
            'favourites' => GenerationResource::collection(
                $user->generations()->where('is_favourite', true)->latest()->limit(20)->get()
            ),
            // "Reuse settings" from the gallery: prefill the form with an earlier generation's settings.
            'reuse' => is_string($reuse)
                ? fn () => ($g = $user->generations()->whereKey($reuse)->first()) ? (new GenerationResource($g))->resolve($request) : null
                : null,
            'focus' => $request->query('g'),
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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Generation started. Its status updates live.']);

        return back();
    }

    /**
     * "Try again" on a failed generation: a brand-new generation with the same settings, estimated and
     * reserved like any other. (Pixelforge says internal_error failures usually succeed on retry.)
     */
    public function retry(Request $request, Generation $generation, CreateGeneration $createGeneration): RedirectResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        abort_unless($generation->status === GenerationStatus::Failed, 422, 'Only failed generations can be retried.');

        try {
            $retry = $createGeneration->handle(
                $request->user(),
                $generation->prompt,
                $generation->size,
                $generation->quality,
                $generation->reference_images,
            );
        } catch (InsufficientCreditsException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => sprintf(
                'This could cost up to %s and you have %s. Buy credits to try again.',
                Money::format($e->requiredNanos),
                Money::format($e->balanceNanos),
            )]);

            return back();
        } catch (PixelforgeException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'We can’t reach Pixelforge right now. Try again in a minute.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trying again with the same settings.']);

        return to_route('dashboard', ['g' => $retry->id]);
    }
}
