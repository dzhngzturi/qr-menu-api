<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule; // <-- ДОБАВИ ТОВА

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Невалидни данни за вход.'],
            ]);
        }

        $token = $user->createToken('admin-panel')->plainTextToken;

        return response()->json([
            'token'    => $token,
            'is_admin' => (bool)$user->is_admin,
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
            'password' => ['sometimes','nullable','string','min:8','confirmed'], // password + password_confirmation
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
