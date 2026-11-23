<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Spendable credit in nanodollars (1 USD = 1,000,000,000). Held amounts are already deducted.
            $table->bigInteger('balance_nanos')->default(0);
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance_nanos', 'is_admin']);
        });
    }
};
