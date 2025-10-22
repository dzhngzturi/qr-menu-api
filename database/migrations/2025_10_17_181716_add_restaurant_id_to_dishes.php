<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('dishes', function (Blueprint $t) {
            $t->foreignId('restaurant_id')->after('id')->constrained()->cascadeOnDelete();
            // увери се, че category_id вече е FK -> categories(id)
        });
    }
    public function down(): void {
        Schema::table('dishes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('restaurant_id');
        });
    }
};
