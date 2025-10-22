<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllergenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rid = (int) ($this->attributes->get('restaurant_id') ?? 0);
        $id  = (int) ($this->route('allergen')?->id ?? 0);

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('allergens', 'code')
                    ->ignore($id)
                    ->where(fn($q) => $q->where('restaurant_id', $rid)),
            ],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
