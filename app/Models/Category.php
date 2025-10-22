<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'restaurant_id',   // <-- важно!
        'name',
        'slug',
        'is_active',
        'image_path',
        'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'position'  => 'integer',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function dishes()
    {
        return $this->hasMany(Dish::class);
    }

    // По желание: удобен скоуп
    public function scopeForRestaurant($q, $rid)
    {
        return $q->where('restaurant_id', $rid);
    }
}
