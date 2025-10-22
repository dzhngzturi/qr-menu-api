<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('restaurants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->json('settings')->nullable(); // логo, тема, валута и др.
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('restaurants'); }
};
