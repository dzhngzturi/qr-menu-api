<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // помощна – връща масив с данни за ресторант или null
    private function restaurantPayload(?\App\Models\Restaurant $r): ?array
    {
        if (!$r) return null;
        return [
            'id'   => $r->id,
            'slug' => $r->slug,
            'name' => $r->name,
        ];
    }


    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        $email = Str::lower($data['email']);
        $ip    = $request->ip();

        // Ключове
        $base   = "login:{$email}|{$ip}";
        $countK = "{$base}:count"; // брояч (24ч)
        $lockK  = "{$base}:lock";  // заключване

        // 1) Активен lock?
        $lockSec = RateLimiter::availableIn($lockK);
        if ($lockSec > 0) {
            return response()->json([
                'message' => 'Акаунтът е временно заключен. Опитай отново след '.ceil($lockSec/60).' мин.'
            ], 429);
        }

        // 2) Проверка на креденшъли
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            // Брояч за последните 24 часа
            $current = RateLimiter::attempts($countK) + 1;
            RateLimiter::hit($countK, 24 * 60 * 60); // пази броя опити 24 часа

            // 3) Прагова логика → слагаме LOCK с точен TTL
            if ($current >= 20) {
                RateLimiter::hit($lockK, 24 * 60 * 60);        // 24 часа
            } elseif ($current >= 15) {
                RateLimiter::hit($lockK, 30 * 60);             // 30 мин
            } elseif ($current >= 5) {
                RateLimiter::hit($lockK, 15 * 60);             // 15 мин
            }
            // Няма нужда от хитове с 60 сек – заключването е отделно

            throw ValidationException::withMessages([
                'email' => ['Невалидни данни за вход.'],
            ]);
        }

        // 4) УСПЕХ → изчисти брояча и заключването
        RateLimiter::clear($countK);
        RateLimiter::clear($lockK);

        $token = $user->createToken('admin-panel')->plainTextToken;

        // (ако вече връщаш restaurant – остави както е при теб)
        return response()->json([
            'token'    => $token,
            'is_admin' => (bool) $user->is_admin,
            'user'     => ['id'=>$user->id, 'name'=>$user->name, 'email'=>$user->email],
            // 'restaurant' => $this->restaurantPayload($user->is_admin ? null : $user->primaryRestaurant()),
        ]);
    }


    
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }



    public function me(Request $request)
    {
        $u = $request->user();
        $restaurant = $u->is_admin ? null : $u->primaryRestaurant();

        return response()->json([
            'user'       => ['id'=>$u->id, 'name'=>$u->name, 'email'=>$u->email],
            'is_admin'   => (bool)$u->is_admin,
            'restaurant' => $this->restaurantPayload($restaurant),
        ]);
    }

    
    
    public function update(Request $r)
    {
        $user = $r->user();

        $data = $r->validate([
            'name'     => ['sometimes','string','max:120'],
            'email'    => ['sometimes','email','max:120', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['sometimes','nullable','string','min:8','confirmed'],
        ]);

        if (array_key_exists('password', $data) && $data['password']) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'user'    => $user->only('id','name','email'),
            'message' => 'Профилът е обновен.',
        ]);
    }


    public function checkRestaurant(Request $request)
    {
        // Този endpoint ще се вика ЗАДЪЛЖИТЕЛНО с middleware:
        // auth:sanctum + resolve.restaurant + restaurant.admin
        // Ако middleware-ите пуснат заявката -> значи има достъп
        $r = $request->attributes->get('restaurant');

        return response()->json([
            'ok' => true,
            'restaurant' => $this->restaurantPayload($r),
        ]);
    }

}
