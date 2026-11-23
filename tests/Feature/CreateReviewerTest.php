<?php

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an admin reviewer with credit and a printed password', function () {
    $this->artisan('app:create-reviewer', ['email' => 'reviewer@example.com'])
        ->expectsOutputToContain('Reviewer account ready')
        ->assertSuccessful();

    $user = User::where('email', 'reviewer@example.com')->sole();
    expect($user->is_admin)->toBeTrue()
        ->and($user->balance_nanos)->toBe(25_000_000_000)
        ->and((int) CreditTransaction::where('user_id', $user->id)->sum('amount_nanos'))->toBe(25_000_000_000);
});

it('tops up rather than stacking credit when run again, and resets the password', function () {
    $this->artisan('app:create-reviewer', ['email' => 'reviewer@example.com']);
    $user = User::where('email', 'reviewer@example.com')->sole();
    $user->forceFill(['balance_nanos' => 20_000_000_000])->save();
    CreditTransaction::create(['user_id' => $user->id, 'type' => 'charge', 'amount_nanos' => -5_000_000_000, 'balance_after_nanos' => 20_000_000_000]);
    $oldHash = $user->password;

    $this->artisan('app:create-reviewer', ['email' => 'reviewer@example.com'])->assertSuccessful();

    $user->refresh();
    expect($user->balance_nanos)->toBe(25_000_000_000)
        ->and($user->password)->not->toBe($oldHash)
        ->and(Hash::check('password', $user->password))->toBeFalse();
});
