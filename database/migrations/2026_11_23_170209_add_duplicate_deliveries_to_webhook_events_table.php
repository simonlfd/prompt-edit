<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            // How many times Pixelforge re-delivered this event after the first; each was ignored.
            $table->unsignedInteger('duplicate_deliveries')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropColumn('duplicate_deliveries');
        });
    }
};
