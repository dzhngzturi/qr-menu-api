<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use App\Models\{Restaurant, Category};

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // 1) Кой ресторант seed-ваме (можеш да подадеш и SEED_RESTAURANT_SLUG=viva в .env)
        $restaurantSlug = env('SEED_RESTAURANT_SLUG', 'viva');
        $restaurantName = env('SEED_RESTAURANT_NAME', 'Viva');

        $restaurant = Restaurant::firstOrCreate(
            ['slug' => $restaurantSlug],
            ['name' => $restaurantName]
        );
        $rid = $restaurant->id;

        // 2) Категории: slug => име
        $categories = [
            'alaminuti'             => 'Аламинути',
            'alkoholni-napitki'     => 'Алкохолни напитки',
            'bezalkoholni-napitki'  => 'Безалкохолни напитки',
            'bira'                  => 'Бира',
            'deserti'               => 'Десерти',
            'qdki'                  => 'Ядки',
            'sandvichi'             => 'Сандвичи',
            'studeni-napitki'       => 'Студени напитки',
            'suhi-mezeta'           => 'Сухи мезета',
            'topli-napitki'         => 'Топли напитки',
            'vino'                  => 'Вино',
        ];

        // 3) Мап: slug => ИМЕ НА ФАЙЛ в database/seeders/images/categories
        // (ако го има – копираме го; пазим ВЪТРЕШЕН път в БД без "storage/")
        $imageMap = [
            'alaminuti'            => 'alaminuti.jpg',
            'alkoholni-napitki'    => 'alkoholni-napitki.jpeg',
            'bezalkoholni-napitki' => 'bezalkoholni-napitki.jpeg',
            'bira'                 => 'biri.jpg',
            'deserti'              => 'desert.jpg',
            'qdki'                 => 'qdki.jpg',
            'sandvichi'            => 'sandvichi.jpg',
            'studeni-napitki'      => 'studeni-napitki.jpeg',
            'suhi-mezeta'          => 'suhi-mezeta.jpeg',
            'topli-napitki'        => 'topli-napitki.jpeg',
            'vino'                 => 'vina.jpg',
        ];

        $seedImageDir = database_path('seeders/images/categories');
        $destDir      = "uploads/restaurants/{$rid}/categories";

        // 4) Създай/обнови категориите за ТОЗИ ресторант
        $pos = 1;
        foreach ($categories as $slug => $name) {
            $cat = Category::updateOrCreate(
                ['restaurant_id' => $rid, 'slug' => $slug],  // уникално за ресторанта
                [
                    'restaurant_id' => $rid,
                    'name'          => $name,
                    'is_active'     => true,
                ]
            );

            // позиция (1..N)
            if ((int) $cat->position !== $pos) {
                $cat->position = $pos;
                $cat->save();
            }
            $pos++;

            // 5) Ако има seed-изображение – копирай и запиши вътрешния път (без "storage/")
            if (isset($imageMap[$slug])) {
                $src = $seedImageDir . DIRECTORY_SEPARATOR . $imageMap[$slug];
                if (is_file($src)) {
                    // запазваме оригиналното име от map-а (може и "{$slug}.ext" – твоя преценка)
                    $dest = $destDir . '/' . $imageMap[$slug];
                    Storage::disk('public')->put($dest, file_get_contents($src));

                    if ($cat->image_path !== $dest) {
                        $cat->image_path = $dest; // вътрешен път, НЕ добавяме "storage/"
                        $cat->save();
                    }
                }
            }
        }
    }
}
