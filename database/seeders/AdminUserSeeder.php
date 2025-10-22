<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Разрешаваме seed само ако е изрично включен
        if (!filter_var(env('SEED_ENABLE_ADMIN', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $email = env('SEED_ADMIN_EMAIL');         
        $pass  = env('SEED_ADMIN_PASSWORD');    
        $name  = env('SEED_ADMIN_NAME', 'Admin');

        // Ако липсват — не правим нищо (особено важно в production)
        if (!$email || !$pass) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => Hash::make($pass),
                'is_admin'          => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
