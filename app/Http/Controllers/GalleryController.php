<?php

namespace App\Http\Controllers;

use App\Generations\GenerationStatus;
use App\Http\Resources\GenerationResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public const FILTERS = ['all', 'favourites', 'ready', 'failed'];

    public const SORTS = ['newest', 'oldest', 'cost'];

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(self::FILTERS)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ]);

        $filter = $data['filter'] ?? 'all';
        $sort = $data['sort'] ?? 'newest';
        $user = $request->user();

        $query = $user->generations()
            ->when($filter === 'favourites', fn ($q) => $q->where('is_favourite', true))
            ->when($filter === 'ready', fn ($q) => $q->where('status', GenerationStatus::Succeeded))
            ->when($filter === 'failed', fn ($q) => $q->where('status', GenerationStatus::Failed));

        match ($sort) {
            'oldest' => $query->oldest(),
            // Most expensive first; unfinished ones (no actual cost yet) sort by their estimate.
            'cost' => $query->orderByRaw('COALESCE(actual_charge_nanos, estimated_charge_nanos) DESC')->latest(),
            default => $query->latest(),
        };

        return Inertia::render('Gallery', [
            'generations' => GenerationResource::collection($query->paginate(24)->withQueryString()),
            'filter' => $filter,
            'markup_percent' => (int) config('billing.markup_percent'),
            'sort' => $sort,
            'counts' => [
                'all' => $user->generations()->count(),
                'favourites' => $user->generations()->where('is_favourite', true)->count(),
                'ready' => $user->generations()->where('status', GenerationStatus::Succeeded)->count(),
                'failed' => $user->generations()->where('status', GenerationStatus::Failed)->count(),
            ],
        ]);
    }
}
