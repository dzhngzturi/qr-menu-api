<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\{Restaurant, User};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestaurantUserController extends Controller
{
    public function index(Restaurant $restaurant)
    {
        return $restaurant->users()
            ->select('users.id','users.name','users.email','restaurant_user.role')
            ->orderBy('users.name')
            ->get();
    }

    public function attach(Request $r, Restaurant $restaurant)
    {
        $data = $r->validate([
            'email'    => ['required','email','max:255'],
            'password' => ['nullable','min:6'], // ще бъде изискуема само ако създаваме нов потребител
            'name'     => ['nullable','string','max:120'],
            'role'     => ['sometimes','in:owner,manager,staff'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            // за нов потребител паролата е задължителна
            if (!$r->filled('password')) {
                return response()->json([
                    'message' => 'Паролата е задължителна за нов потребител.'
                ], 422);
            }

            $user = User::create([
                'name'     => $data['name'] ?? Str::before($data['email'], '@'),
                'email'    => $data['email'],
                'password' => bcrypt($data['password']),
            ]);
        }

        $role = $data['role'] ?? 'owner';

        $restaurant->users()->syncWithoutDetaching([
            $user->id => ['role' => $role]
        ]);

        return response()->json([
            'ok'   => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $role,
            ],
        ]);
    }

    public function detach(Restaurant $restaurant, User $user)
    {
        $restaurant->users()->detach($user->id);
        return response()->json(['ok' => true]);
    }
}
