<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->ulid('id')->primary(); // Also sent to Pixelforge as the Idempotency-Key and in metadata.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_id')->nullable()->unique(); // gen_…
            $table->string('status')->index(); // pending, queued, processing, succeeded, failed

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images');

            // Rates (decimal strings, USD per 1M tokens) and token usage as reported by Pixelforge.
            $table->json('rates');
            $table->json('usage')->nullable();

            // All money is integer nanodollars. Provider = what Pixelforge charges us, charge = what we charge the user.
            $table->bigInteger('estimated_provider_nanos');
            $table->bigInteger('estimated_charge_nanos');
            $table->bigInteger('max_charge_nanos'); // Worst case; this is what we hold from the balance.
            $table->bigInteger('actual_provider_nanos')->nullable();
            $table->bigInteger('actual_charge_nanos')->nullable();

            $table->string('image_url', 2048)->nullable();
            $table->timestamp('image_url_expires_at')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('submit_attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable(); // Set while waiting for Pixelforge capacity.

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index(); // Pixelforge bills on the UTC day of completion.
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
