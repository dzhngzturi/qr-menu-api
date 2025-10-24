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
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        $email = Str::lower($data['email']);
        $key   = $email.'|'.$request->ip();

        // 1) АКТИВЕН COOLDOWN? → върни 429 (важно: без значение, че опитите са < 20)
        $attempts = RateLimiter::attempts($key);
        $cooldown = RateLimiter::availableIn($key); // секунди до отпадане
        if ($cooldown > 0 && $attempts >= 5) {
            return response()->json([
                'message' => 'Акаунтът е временно заключен. Опитай отново след '.ceil($cooldown/60).' мин.'
            ], 429);
        }

        // 2) Проверка на креденшъли
        $user = \App\Models\User::where('email', $email)->first();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            $attempts++; // смятаме следващия опит

            // 3) Прагова логика: 5→15мин, 15→30мин, 20→24ч
            if ($attempts >= 20) {
                RateLimiter::hit($key, 24 * 60 * 60);     // 24 часа
            } elseif ($attempts >= 15) {
                RateLimiter::hit($key, 30 * 60);          // 30 мин
            } elseif ($attempts >= 5) {
                RateLimiter::hit($key, 15 * 60);          // 15 мин
            } else {
                RateLimiter::hit($key, 24 * 60 * 60);     // пазим история до 24ч
            }

            // 4) Съвместимо с фронтенда: 422 с поле "email"
            throw ValidationException::withMessages([
                'email' => ['Невалидни данни за вход.'],
            ]);
        }

        // 5) Успех → чистим брояча и връщаме токен
        RateLimiter::clear($key);

        $token = $user->createToken('admin-panel')->plainTextToken;

        return response()->json([
            'token'    => $token,
            'is_admin' => (bool) $user->is_admin,
            'user'     => ['id'=>$user->id, 'name'=>$user->name, 'email'=>$user->email],
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

        return response()->json([
            'user'     => ['id'=>$u->id, 'name'=>$u->name, 'email'=>$u->email],
            'is_admin' => (bool)$u->is_admin,
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
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'user'    => $user->only('id','name','email'),
            'message' => 'Профилът е обновен.',
        ]);
    }
}
