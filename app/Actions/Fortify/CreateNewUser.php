<?php

namespace App\Actions\Fortify;

use App\Billing\CreditLedger;
use App\Billing\Money;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $credit = (int) config('billing.signup_credit_dollars');
            if ($credit > 0) {
                app(CreditLedger::class)->purchase($user, $credit * Money::NANOS_PER_DOLLAR, 'Welcome credit');
            }

            return $user->refresh();
        });
    }
}
