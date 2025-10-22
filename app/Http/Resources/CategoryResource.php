<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'position'     => $this->position,                 // ако колоната съществува
            'is_active'    => (bool) $this->is_active,
            // image_path пази вътрешния път (напр. uploads/restaurants/1/categories/x.jpg)
            // Storage::url() връща публичния URL (изисква php artisan storage:link)
            'image_url' => $this->image_path ? url(Storage::url($this->image_path)) : null,
            // броя на ястията (ще се върне само ако е зареден withCount('dishes'))
            'dishes_count' => $this->whenCounted('dishes'),

            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
