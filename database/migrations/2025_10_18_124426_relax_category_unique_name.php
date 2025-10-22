<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // helper за проверка на индекс по име
            $hasIndex = function (string $name): bool {
                $rows = DB::select("SHOW INDEX FROM `categories` WHERE `Key_name` = ?", [$name]);
                return !empty($rows);
            };

            // махни каквото намериш от старите unique индекси по name/slug
            if ($hasIndex('cat_restaurant_name_unique')) {
                $table->dropUnique('cat_restaurant_name_unique');
            }
            if ($hasIndex('categories_name_unique')) {
                $table->dropUnique('categories_name_unique');
            }
            if ($hasIndex('categories_slug_unique')) {
                $table->dropUnique('categories_slug_unique');
            }

            // гарантирай уникалност само по (restaurant_id, slug)
            if (!$hasIndex('cat_restaurant_slug_unique')) {
                $table->unique(['restaurant_id', 'slug'], 'cat_restaurant_slug_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // връщане назад (по избор)
            $hasIndex = function (string $name): bool {
                $rows = DB::select("SHOW INDEX FROM `categories` WHERE `Key_name` = ?", [$name]);
                return !empty($rows);
            };

            if ($hasIndex('cat_restaurant_slug_unique')) {
                $table->dropUnique('cat_restaurant_slug_unique');
            }
            // ако искаш да върнеш старите глобални:
            if (!$hasIndex('categories_name_unique')) {
                $table->unique('name', 'categories_name_unique');
            }
            if (!$hasIndex('categories_slug_unique')) {
                $table->unique('slug', 'categories_slug_unique');
            }
        });
    }
};
