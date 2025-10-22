<?php
// database/migrations/2025_01_01_000000_create_allergens_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('allergens', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('restaurant_id')->index();
            $t->string('code', 10)->index();     // напр. A1, A3, A6...
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
            $t->unique(['restaurant_id','code']); // уникален код по ресторант
        });

        Schema::create('allergen_dish', function (Blueprint $t) {
            $t->unsignedBigInteger('dish_id')->index();
            $t->unsignedBigInteger('allergen_id')->index();

            $t->primary(['dish_id','allergen_id']);
            $t->foreign('dish_id')->references('id')->on('dishes')->cascadeOnDelete();
            $t->foreign('allergen_id')->references('id')->on('allergens')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('allergen_dish');
        Schema::dropIfExists('allergens');
    }
};
