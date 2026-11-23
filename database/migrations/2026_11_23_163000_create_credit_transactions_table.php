<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // purchase, hold, release, charge
            $table->bigInteger('amount_nanos'); // Signed: positive adds to the balance, negative removes.
            $table->bigInteger('balance_after_nanos');
            $table->string('description')->nullable();
            $table->timestamps();

            // A generation can be held, released and charged at most once each.
            $table->unique(['generation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
