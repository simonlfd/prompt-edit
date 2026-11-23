<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique(); // evt_… — the dedup key.
            $table->string('type');
            $table->string('provider_generation_id')->nullable()->index();
            $table->foreignUlid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('delivery_attempt')->nullable();
            $table->string('outcome'); // applied, ignored_stale, unmatched, ping
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
