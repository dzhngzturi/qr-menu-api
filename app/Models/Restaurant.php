<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    protected $fillable = ['name', 'slug', 'settings'];
    protected $casts    = ['settings' => 'array'];

    /** Users (many-to-many via restaurant_user) */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'restaurant_user')
            ->withPivot('role')       // owner | manager | staff
            ->withTimestamps();
    }

    /** Categories */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** Dishes */
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    // За API може да оставиш id; ако някога искаш route model binding по slug – смени на 'slug'
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
