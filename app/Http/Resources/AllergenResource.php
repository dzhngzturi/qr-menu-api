<?php
// app/Http/Resources/AllergenResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AllergenResource extends JsonResource {
    public function toArray($request) {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => (bool)$this->is_active,
        ];
    }
}
