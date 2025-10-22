<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rid = $this->attributes->get('restaurant_id'); // текущ ресторант
        $id  = $this->route('dish')?->id;               // ID на ястието

        return [
            'category_id' => [
                'sometimes','integer',
                Rule::exists('categories','id')->where('restaurant_id', $rid),
            ],
            'name'        => [
                'sometimes','string','max:160',
                Rule::unique('dishes','name')->ignore($id)->where('restaurant_id', $rid),
            ],
            'description' => ['sometimes','nullable','string'],
            'price'       => ['sometimes','numeric','min:0'],
            'image'       => ['sometimes','nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'is_active'   => ['sometimes','boolean'],

            // ▶ АЛЕРГЕНИ
            // при PATCH може да липсва; ако дойде, очакваме масив
            'allergens'   => ['sometimes','array'],
            'allergens.*' => [
                'integer',
                Rule::exists('allergens','id')->where('restaurant_id', $rid),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('is_active')) {
            $payload['is_active'] = $this->boolean('is_active');
        }

        if ($this->has('allergens')) {
            $al = $this->input('allergens');
            if (!is_array($al)) {
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
        }

        if ($payload !== []) $this->merge($payload);
    }
}
