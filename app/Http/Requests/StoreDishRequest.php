<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // минаваш през admin middleware
    }

    public function rules(): array
    {
        $rid = $this->attributes->get('restaurant_id'); // идва от ResolveRestaurant

        return [
            // категорията ТРЯБВА да е от същия ресторант
            'category_id' => [
                'required','integer',
                Rule::exists('categories','id')->where('restaurant_id', $rid),
            ],
            // по желание: уникално име в рамките на ресторанта
            'name'        => [
                'required','string','max:160',
                Rule::unique('dishes','name')->where('restaurant_id', $rid),
            ],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric','min:0'],
            'image'       => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'is_active'   => ['boolean'],

            // ▶ АЛЕРГЕНИ
            'allergens'   => ['nullable','array'], // може да липсва или да е празен масив
            'allergens.*' => [
                'integer',
                Rule::exists('allergens','id')->where('restaurant_id', $rid),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // нормализиране на is_active
        $payload = ['is_active' => $this->boolean('is_active')];

        // нормализиране на allergens: приеми '' / null / one value / CSV / масив → към уникален int масив
        $al = $this->input('allergens');
        if (!is_array($al)) {
            // ако дойде единична стойност или CSV от фронта
            if (is_string($al) && trim($al) !== '') {
                $al = array_map('trim', explode(',', $al));
            } elseif ($al === null || $al === '') {
                $al = [];
            } else {
                $al = [$al];
            }
        }
        $al = array_values(array_unique(array_map(static fn($v) => (int)$v, $al)));
        $payload['allergens'] = $al;

        $this->merge($payload);
    }
}
