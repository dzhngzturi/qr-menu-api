<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = ['name','email','password','is_admin'];
    protected $hidden   = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
        ];
    }

    // many-to-many към ресторанти
    public function restaurants()
    {
        // ако таблицата е 'restaurant_user' (както на скрийншота) – изрично подай името
        return $this->belongsToMany(Restaurant::class, 'restaurant_user')
            ->withPivot('role')       // owner | manager | staff
            ->withTimestamps();
    }

    // ▼ ДОБАВИ ТОВА
    public function primaryRestaurant(): ?Restaurant
    {
        return $this->restaurants()
            ->orderByRaw("FIELD(restaurant_user.role,'owner','manager','staff')")
            ->orderBy('restaurant_user.created_at')
            ->first();
    }

    public function isSuperadmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function hasRestaurant(int $restaurantId): bool
    {
        return $this->restaurants()->whereKey($restaurantId)->exists();
    }
}
