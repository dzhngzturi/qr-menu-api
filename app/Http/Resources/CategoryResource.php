<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $ver = null;
        if ($this->image_path) {
            try {
                $ver = Storage::disk('public')->lastModified($this->image_path);
            } catch (\Throwable $e) {
                // ignore; ще паднем към updated_at
            }
        }
        $ver = $ver ?? ($this->updated_at?->timestamp ?? time());

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'position'     => $this->position,
            'is_active'    => (bool) $this->is_active,
            'image_url'    => $this->image_path
                ? url(Storage::url($this->image_path)) . '?v=' . $ver
                : null,
            'dishes_count' => $this->whenCounted('dishes'),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}