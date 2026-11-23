<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('generations:reconcile')->everyMinute()->withoutOverlapping();

Artisan::command('user:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->firstOrFail();
    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$user->email} is now an admin.");
})->purpose('Give a user access to the admin spend report');

Schedule::command('usage:check')->hourly()->withoutOverlapping();
