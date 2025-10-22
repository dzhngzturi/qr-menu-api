<?php

// database/migrations/2025_10_20_XXXXXX_add_timestamps_to_allergen_dish_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('allergen_dish', function (Blueprint $table) {
            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::table('allergen_dish', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
