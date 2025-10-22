<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\{Restaurant, Category, Dish};

class DishSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Ресторант за seed (по желание .env: SEED_RESTAURANT_SLUG/NAME)
        $restaurantSlug = env('SEED_RESTAURANT_SLUG', 'viva');
        $restaurantName = env('SEED_RESTAURANT_NAME', 'Viva');

        $restaurant = Restaurant::firstOrCreate(
            ['slug' => $restaurantSlug],
            ['name' => $restaurantName]
        );
        $rid = $restaurant->id;

        // 2) Данни: category_slug => [списък ястия]
        $data = [
            'topli-napitki' => [
                ['name' => 'Кафе лаваца', 'description' => '60 мл.',  'price' => 2.00],
                ['name' => 'Кафе без кофеин', 'description' => '60 мл.', 'price' => 2.00],
                ['name' => 'Виенско кафе', 'description' => '60 мл.', 'price' => 3.00],
                ['name' => 'Турски чай', 'description' => '100 мл.', 'price' => 2.00],
                ['name' => 'Билков чай', 'description' => '200 мл.', 'price' => 2.00],
                ['name' => 'Плодов чай', 'description' => '200 мл.', 'price' => 2.00],
                ['name' => 'Нескафе 3в1', 'description' => '100 мл.', 'price' => 2.00],
                ['name' => 'Горещ шоколад', 'description' => '200 мл.', 'price' => 4.00],
                ['name' => 'Капучино', 'description' => '200 мл.', 'price' => 4.00],
                ['name' => 'Мляко с нескафе', 'description' => '200 мл.', 'price' => 4.00],
                ['name' => 'Топло мляко', 'description' => '200 мл.', 'price' => 2.50],
            ],
            'bezalkoholni-napitki' => [
                ['name' => 'Кока Кола', 'description' => '250 мл.', 'price' => 3.00],
                ['name' => 'Кока Кола без захар', 'description' => '250 мл.', 'price' => 3.00],
                ['name' => 'Фанта', 'description' => '250 мл.', 'price' => 3.00],
                ['name' => 'Спрайт', 'description' => '250 мл.', 'price' => 3.00],
                ['name' => 'Швепс тоник', 'description' => '250 мл.', 'price' => 3.00],
                ['name' => 'Натурален сок Cappy', 'description' => '250 мл.', 'price' => 3.00],
                ['name' => 'Редбул', 'description' => '250 мл.', 'price' => 5.00],
                ['name' => 'Минерална вода', 'description' => '500 мл.', 'price' => 1.50],
            ],
            'bira' => [
                ['name' => 'Каменица', 'description' => '500 мл.', 'price' => 3.00],
                ['name' => 'Бекс', 'description' => '500 мл.', 'price' => 3.50],
                ['name' => 'Стела артоа', 'description' => '500 мл.', 'price' => 4.00],
                ['name' => 'Старопрамен', 'description' => '500 мл.', 'price' => 3.50],
                ['name' => 'Корона', 'description' => '355 мл.', 'price' => 5.00],
                ['name' => 'Самърсби', 'description' => '330 мл.', 'price' => 5.00],
            ],
            'alkoholni-napitki' => [
                ['name' => 'Уиски Савой', 'description' => '50 мл.', 'price' => 2.50],
                ['name' => 'Уиски Джеймсън', 'description' => '50 мл.', 'price' => 6.00],
                ['name' => 'Уиски Тюламор дю', 'description' => '50 мл.', 'price' => 5.00],
                ['name' => 'Уиски Джак Даниелс', 'description' => '50 мл.', 'price' => 7.00],
                ['name' => 'Уиски Джони Уокър Ред', 'description' => '50 мл.', 'price' => 5.00],
                ['name' => 'Водка Финландия', 'description' => '50 мл.', 'price' => 4.00],
                ['name' => 'Мента', 'description' => '50 мл.', 'price' => 3.00],
                ['name' => 'Текила', 'description' => '50 мл.', 'price' => 2.00],
                ['name' => 'Ром', 'description' => '50 мл.', 'price' => 3.00],
                ['name' => 'Бейлис', 'description' => '50 мл.', 'price' => 4.00],
                ['name' => 'Водка Савой', 'description' => '50 мл.', 'price' => 2.50],
            ],
            'vino' => [
                ['name' => 'Чаша вино', 'description' => 'Вино', 'price' => 4.00],
            ],
            'sandvichi' => [
                ['name' => 'Сандвич с кашкавал', 'description' => '', 'price' => 3.00],
                ['name' => 'Сандвич със салам и кашкавал', 'description' => '', 'price' => 3.50],
                ['name' => 'Сандвич със суджук и кашкавал', 'description' => '', 'price' => 4.00],
            ],
            'alaminuti' => [
                ['name' => 'Пържени картофи', 'description' => '200 гр.', 'price' => 3.50],
                ['name' => 'Пържени картофи със сирене', 'description' => '200 гр.', 'price' => 5.00],
                ['name' => 'Пилешки хапки', 'description' => '200 гр.', 'price' => 7.00],
                ['name' => 'Цаца', 'description' => '200 гр.', 'price' => 6.00],
            ],
            'qdki' => [
                ['name' => 'Фъстъци', 'description' => '100 гр.', 'price' => 3.00],
                ['name' => 'Бадеми', 'description' => '100 гр.', 'price' => 7.00],
                ['name' => 'Кашу', 'description' => '100 гр.', 'price' => 7.00],
                ['name' => 'Суджук', 'description' => '100 гр.', 'price' => 8.00],
            ],
            'deserti' => [
                ['name' => 'Шоколад', 'description' => '100 гр.', 'price' => 5.00],
            ],
        ];

        // 3) Карта ИМЕ -> ФАЙЛ (в database/seeders/images/dishes)
        $filenameMap = [
            'Кафе лаваца' => 'lavazza.png',
            'Кафе без кофеин' => 'kafe-bez-kofein.jpg',
            'Виенско кафе' => 'viensko-kafe.jpg',
            'Турски чай' => 'turski-chay.jpg',
            'Билков чай' => 'bilkov-chay.jpg',
            'Плодов чай' => 'plodov-chay.jpeg',
            'Нескафе 3в1' => 'neskafe-3v1.jpg',
            'Горещ шоколад' => 'goresht-shokolad.jpg',
            'Капучино' => 'kapuchino.jpg',
            'Мляко с нескафе' => 'mlyako-s-neskafe.jpg',
            'Топло мляко' => 'toplo-mlqko.jpg',

            'Кока Кола' => 'koka-kola.jpg',
            'Кока Кола без захар' => 'koka-kola-bez-zahar.png',
            'Фанта' => 'fanta.png',
            'Спрайт' => 'spayt.jpg',
            'Швепс тоник' => 'shveps-tonik.jpg',
            'Натурален сок Cappy' => 'cappy.png',
            'Редбул' => 'redbull.png',
            'Минерална вода' => 'mineralna-voda.jpg',

            'Каменица' => 'kamenitza.jpg',
            'Бекс' => 'becks.jpg',
            'Стела артоа' => 'stella-artois_png.png',
            'Старопрамен' => 'staropramen.jpg',
            'Корона' => 'corona.png',
            'Самърсби' => 'somersby.png',

            'Уиски Савой' => 'savoy-uisky.jpg',
            'Уиски Джеймсън' => 'Jameson.jpg',
            'Уиски Тюламор дю' => 'tullamore-dew.jpg',
            'Уиски Джак Даниелс' => 'jack-daniels.png',
            'Уиски Джони Уокър Ред' => 'johnnie-walker.png',
            'Водка Финландия' => 'Finlandia.jpg',
            'Мента' => 'menta.png',
            'Текила' => 'tequila.jpg',
            'Ром' => 'savoy-rum.png',
            'Бейлис' => 'baileys.png',
            'Водка Савой' => 'savoy-vodka.jpg',

            'Пържени картофи' => 'purjeni-kartofi.jpg',
            'Пържени картофи със сирене' => 'purjeni-kartofi-sirene.jpg',
            'Пилешки хапки' => 'pileshki-hapki.jpg',
            'Цаца' => 'tsatsa.jpg',

            'Сандвич с кашкавал' => 'sandvich-s-kashkaval.jpg',
            'Сандвич със салам и кашкавал' => 'sandvich-s-salam-kashkaval.jpg',
            'Сандвич със суджук и кашкавал' => 'sandvich-s-sudjuk-kashkaval.jpg',

            'Суджук' => 'sudjuk.jpeg',

            'Чаша вино' => 'cherveno-vino.jpeg',
        ];

        $seedImageDir = database_path('seeders/images/dishes');
        $fallbackExts = ['jpg','jpeg','png','webp'];

        foreach ($data as $categorySlug => $dishes) {
            // 4) Намери категорията за ТОЗИ ресторант
            $category = Category::where('restaurant_id', $rid)
                ->where('slug', $categorySlug)
                ->first();

            if (!$category) {
                // ако липсва – създай placeholder категория
                $category = Category::create([
                    'restaurant_id' => $rid,
                    'slug'          => $categorySlug,
                    'name'          => Str::headline(str_replace('-', ' ', $categorySlug)),
                    'position'      => 0,
                    'is_active'     => true,
                ]);
            }

            foreach ($dishes as $item) {
                $dishName = $item['name'];
                $dishSlug = Str::slug($dishName, '-');

                // вътрешен път, който ще запишем в БД (БЕЗ "storage/")
                $imagePath = null;
                $destDir   = "uploads/restaurants/{$rid}/dishes";

                // 5) Копирай снимка по карта – пазим ОРИГИНАЛНОТО име на файла
                if (isset($filenameMap[$dishName])) {
                    $fileName = $filenameMap[$dishName]; // напр. lavazza.png
                    $src = $seedImageDir . DIRECTORY_SEPARATOR . $fileName;
                    if (is_file($src)) {
                        $dest = $destDir . '/' . $fileName; // същото име
                        Storage::disk('public')->put($dest, file_get_contents($src));
                        $imagePath = $dest;
                    }
                }

                // 6) Fallback: търси slug.{ext} (също пазим вътрешен път без "storage/")
                if (!$imagePath) {
                    foreach ($fallbackExts as $ext) {
                        $candidateName = "{$dishSlug}.{$ext}";
                        $candidate = $seedImageDir . DIRECTORY_SEPARATOR . $candidateName;
                        if (is_file($candidate)) {
                            $dest = $destDir . '/' . $candidateName;
                            Storage::disk('public')->put($dest, file_get_contents($candidate));
                            $imagePath = $dest;
                            break;
                        }
                    }
                }

                // 7) Създай/обнови ястие – вързано към ресторанта
                $dish = Dish::updateOrCreate(
                    ['restaurant_id' => $rid, 'name' => $dishName],
                    [
                        'restaurant_id' => $rid,
                        'category_id'   => $category->id,
                        'description'   => $item['description'] ?? null,
                        'price'         => $item['price'],
                        'is_active'     => true,
                    ]
                );

                if ($imagePath && $dish->image_path !== $imagePath) {
                    $dish->image_path = $imagePath; // ВЪТРЕШЕН път (пример: uploads/restaurants/1/dishes/lavazza.png)
                    $dish->save();
                }
            }
        }
    }
}
