<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->boolean('is_favourite')->default(false);
            $table->index(['user_id', 'is_favourite', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_favourite', 'created_at']);
            $table->dropColumn('is_favourite');
        });
    }
};
