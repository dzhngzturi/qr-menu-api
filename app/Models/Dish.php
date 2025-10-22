<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dish extends Model
{
    protected $fillable = [
        'restaurant_id', 'category_id','name','description','price','image_path','is_active'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function allergens()
    {
        return $this->belongsToMany(Allergen::class, 'allergen_dish', 'dish_id', 'allergen_id')
            ->withTimestamps();
    }


}

