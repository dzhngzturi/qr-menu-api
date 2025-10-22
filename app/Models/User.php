<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',        // <-- важно за супер-админ
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean', // <-- да се каствa
        ];
    }

    // --- Релации за централно управление ---
    public function restaurants()
    {
        return $this->belongsToMany(Restaurant::class)
            ->withPivot('role')       // owner | manager | staff
            ->withTimestamps();
    }

    // Малък хелпър
    public function isSuperadmin(): bool
    {
        return (bool) $this->is_admin;
    }

    // Проверка дали има достъп до даден ресторант
    public function hasRestaurant(int $restaurantId): bool
    {
        return $this->restaurants()->whereKey($restaurantId)->exists();
    }
}
