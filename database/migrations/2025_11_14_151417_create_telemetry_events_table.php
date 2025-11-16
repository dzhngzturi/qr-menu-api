<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->id();

            // Към кой ресторант е събитието (multi-tenant)
            $table->unsignedBigInteger('restaurant_id')->index();

            // Тип: qr_scan, menu_open, search, ...
            $table->string('type', 50)->index();

            // Основно поле за графиките
            $table->timestamp('occurred_at')->useCurrent()->index();

            // За групиране на потребители
            $table->string('session_id', 100)->nullable()->index();

            // IP + User Agent (анонимни, не са лични данни)
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            // 🔥 ВАЖНО: кратък термин за търсене
            $table->string('search_term', 191)->nullable()->index();

            // Допълнителни данни — JSON
            $table->json('payload')->nullable();

            $table->timestamps();

            // Ресторанти: автоматично триене ако restaurante бъде изтрит
            $table->foreign('restaurant_id')
                  ->references('id')
                  ->on('restaurants')
                  ->cascadeOnDelete();

            // 🔥 Комбиниран индекс за супер бързи заявки
            $table->index(
                ['restaurant_id', 'type', 'occurred_at'],
                'telemetry_restaurant_type_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
    }
};
