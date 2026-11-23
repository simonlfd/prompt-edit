<?php

namespace App\Http\Controllers;

use App\Billing\Money;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A user's own ledger: every change to their balance, in plain language. */
class BillingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $ledger = CreditTransaction::where('user_id', $user->id);

        $releasedIds = CreditTransaction::where('user_id', $user->id)->where('type', CreditTransaction::RELEASE)->select('generation_id');
        $reserved = -(int) CreditTransaction::where('user_id', $user->id)
            ->where('type', CreditTransaction::HOLD)
            ->whereNotIn('generation_id', $releasedIds)
            ->sum('amount_nanos');

        $entries = $ledger->clone()
            ->with('generation:id,prompt,status')
            ->latest('id')
            ->paginate(30)
            ->through(fn (CreditTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => Money::toDecimal($t->amount_nanos),
                'balance_after' => Money::toDecimal($t->balance_after_nanos),
                'description' => $t->description,
                'created_at' => $t->created_at?->toIso8601String(),
                'generation' => $t->generation ? [
                    'id' => $t->generation->id,
                    'prompt' => $t->generation->prompt,
                    'status' => $t->generation->status->value,
                ] : null,
            ]);

        return Inertia::render('Billing', [
            'summary' => [
                'bought' => Money::toDecimal((int) $ledger->clone()->where('type', CreditTransaction::PURCHASE)->sum('amount_nanos')),
                'spent' => Money::toDecimal(-(int) $ledger->clone()->where('type', CreditTransaction::CHARGE)->sum('amount_nanos')),
                'reserved' => Money::toDecimal($reserved),
                'images_paid_for' => $ledger->clone()->where('type', CreditTransaction::CHARGE)->count(),
            ],
            'entries' => $entries,
            'credit_packs' => config('billing.credit_packs'),
        ]);
    }
}
