<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void {
        Schema::table('dishes', function (Blueprint $t) {
            $t->unsignedSmallInteger('position')->default(0)->index()->after('price');
        });
        Schema::table('allergens', function (Blueprint $t) {
            $t->unsignedSmallInteger('position')->default(0)->index()->after('name');
        });
    }
    public function down(): void {
        Schema::table('dishes', fn (Blueprint $t) => $t->dropColumn('position'));
        Schema::table('allergens', fn (Blueprint $t) => $t->dropColumn('position'));
    }
};
