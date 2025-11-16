<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DishResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'is_active'   => (bool) $this->is_active,

            'image_url' => $this->image_path
            ? url(Storage::url($this->image_path)) . '?v=' . ($this->updated_at?->timestamp ?? time())
            : null,

            // когато в контролера си направил ->with('category:id,name')
            'category'    => $this->whenLoaded('category', function () {
                return [
                    'id'   => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),

            // когато си направил ->with('allergens:id,code,name')
            'allergens'   => $this->whenLoaded('allergens', function () {
                return $this->allergens->map(fn($a) => [
                    'id'   => $a->id,
                    'code' => $a->code,
                    'name' => $a->name,
                ]);
            }),

            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
