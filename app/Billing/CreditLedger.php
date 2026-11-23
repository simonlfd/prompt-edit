<?php

namespace App\Billing;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every balance change goes through here and writes a ledger row, so a user's balance always equals the
 * sum of their transactions.
 *
 * A generation's lifecycle on the ledger:
 *   hold (−max possible charge) → release (+the same amount) and charge (−actual), or just release if it failed.
 * Holding the worst case means a finished generation can never take a user below zero.
 * The unique (generation_id, type) index makes each step happen at most once, even if the code above it is wrong.
 */
class CreditLedger
{
    public function purchase(User $user, int $nanos, string $description): CreditTransaction
    {
        return DB::transaction(fn () => $this->record($this->lockUser($user->id), $nanos, CreditTransaction::PURCHASE, null, $description));
    }

    /** @throws InsufficientCreditsException */
    public function hold(Generation $generation): void
    {
        $user = $this->lockUser($generation->user_id);

        if ($user->balance_nanos < $generation->max_charge_nanos) {
            throw new InsufficientCreditsException($generation->max_charge_nanos, $user->balance_nanos);
        }

        $this->record($user, -$generation->max_charge_nanos, CreditTransaction::HOLD, $generation->id, 'Reserved for generation');
    }

    /** Release the hold and charge the actual cost. Call inside the transaction that marks it succeeded. */
    public function settle(Generation $generation): void
    {
        $user = $this->lockUser($generation->user_id);
        $this->releaseHold($user, $generation);
        $this->record($user, -$generation->actual_charge_nanos, CreditTransaction::CHARGE, $generation->id, 'Generation charge');
    }

    /** Give back the hold for a generation that failed. Call inside the transaction that marks it failed. */
    public function release(Generation $generation): void
    {
        $this->releaseHold($this->lockUser($generation->user_id), $generation);
    }

    private function releaseHold(User $user, Generation $generation): void
    {
        $held = CreditTransaction::where('generation_id', $generation->id)->where('type', CreditTransaction::HOLD)->first();
        $released = CreditTransaction::where('generation_id', $generation->id)->where('type', CreditTransaction::RELEASE)->exists();

        if ($held && ! $released) {
            $this->record($user, -$held->amount_nanos, CreditTransaction::RELEASE, $generation->id, 'Reservation released');
        }
    }

    private function lockUser(int $userId): User
    {
        return User::whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    private function record(User $user, int $amount, string $type, ?string $generationId, string $description): CreditTransaction
    {
        $user->balance_nanos += $amount;
        $user->save();

        return $user->creditTransactions()->create([
            'generation_id' => $generationId,
            'type' => $type,
            'amount_nanos' => $amount,
            'balance_after_nanos' => $user->balance_nanos,
            'description' => $description,
        ]);
    }
}
