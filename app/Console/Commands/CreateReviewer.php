<?php

namespace App\Console\Commands;

use App\Billing\CreditLedger;
use App\Billing\Money;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Creates (or resets) an admin login for reviewers, with credit to try things out. The password is
 * generated and printed once, so it never lives in the repository.
 */
#[AsCommand(name: 'app:create-reviewer', description: 'Create an admin account with credit for reviewers')]
class CreateReviewer extends Command
{
    protected $signature = 'app:create-reviewer {email} {--name=Reviewer} {--credit=25 : Dollars of credit to top up to}';

    public function handle(CreditLedger $ledger): int
    {
        $password = Str::password(16, symbols: false);

        $user = DB::transaction(function () use ($ledger, $password) {
            $user = User::firstOrNew(['email' => $this->argument('email')]);
            $user->forceFill([
                'name' => $this->option('name'),
                'password' => $password,
                'is_admin' => true,
            ])->save();

            $target = (int) $this->option('credit') * Money::NANOS_PER_DOLLAR;
            $topUp = $target - $user->fresh()->balance_nanos;

            if ($topUp > 0) {
                $ledger->purchase($user, $topUp, 'Reviewer credit');
            }

            return $user->fresh();
        });

        $this->components->info('Reviewer account ready.');
        $this->components->twoColumnDetail('Email', $user->email);
        $this->components->twoColumnDetail('Password', $password);
        $this->components->twoColumnDetail('Balance', Money::format($user->balance_nanos));
        $this->newLine();
        $this->line('  Share these through the application form, not the repository.');

        return self::SUCCESS;
    }
}
