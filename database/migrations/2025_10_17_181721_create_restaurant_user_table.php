<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('restaurant_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role')->default('owner'); // owner|manager|staff
            $t->timestamps();
            $t->unique(['restaurant_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('restaurant_user'); }
};
