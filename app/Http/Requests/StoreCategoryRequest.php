<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Админ middleware вече е минато
        return true;
    }

    public function rules(): array
    {
        $rid = $this->attributes->get('restaurant_id'); // от ResolveRestaurant

        return [
            'name'      => [
                'required','string','max:255',
                Rule::unique('categories','name')->where('restaurant_id', $rid),
            ],
            'slug'      => [
                'nullable','string','max:255',
                Rule::unique('categories','slug')->where('restaurant_id', $rid),
            ],
            'position'  => ['nullable','integer','min:0'],
            'is_active' => ['nullable','boolean'],
            'image'     => ['nullable','image','max:5120'], // 5MB
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->filled('slug')
            ? Str::slug($this->input('slug'), '-')
            : $this->input('slug');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'slug'      => $slug,
        ]);
    }
}
