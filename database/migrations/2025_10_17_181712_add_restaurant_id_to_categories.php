<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('categories', function (Blueprint $t) {
            $t->foreignId('restaurant_id')->after('id')->constrained()->cascadeOnDelete();
            $t->unique(['restaurant_id','name']);
        });
    }
    public function down(): void {
        Schema::table('categories', function (Blueprint $t) {
            $t->dropUnique(['restaurant_id','name']);
            $t->dropConstrainedForeignId('restaurant_id');
        });
    }
};
