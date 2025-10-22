<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rid = $this->attributes->get('restaurant_id');        // текущ ресторант
        $id  = $this->route('category')?->id;                  // ID на редактираната категория

        return [
            'name'      => [
                'sometimes','string','max:255',
                Rule::unique('categories','name')
                    ->ignore($id)
                    ->where('restaurant_id', $rid),
            ],
            'slug'      => [
                'sometimes','nullable','string','max:255',
                Rule::unique('categories','slug')
                    ->ignore($id)
                    ->where('restaurant_id', $rid),
            ],
            'position'  => ['sometimes','integer','min:0'],
            'is_active' => ['sometimes','boolean'],
            'image'     => ['sometimes','nullable','image','max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->filled('slug')
            ? Str::slug($this->input('slug'), '-')
            : $this->input('slug');

        // Не презаписваме стойности, ако не са подадени
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : $this->input('is_active'),
            'slug'      => $slug,
        ]);
    }
}
