<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class BlockLockedLogin
{
    public function handle(Request $request, Closure $next)
    {
        // пускаме проверката само за login endpoint-а (ако роутът е /api/auth/login)
        if ($request->is('api/auth/login')) {
            $email = strtolower((string) $request->input('email', ''));
            if ($email !== '') {
                $ip   = $request->ip();
                $base = "login:{$email}|{$ip}";
                $lockK = "{$base}:lock";

                $lockSec = RateLimiter::availableIn($lockK);
                if ($lockSec > 0) {
                    // евтин отговор, без да стигаме до контролера/БД
                    return response()
                        ->json([
                            'message'      => 'Акаунтът е временно заключен.',
                            'retry_in'     => $lockSec,
                            'locked_until' => now()->addSeconds($lockSec)->toIso8601String(),
                        ], 429)
                        ->header('Retry-After', $lockSec);
                }
            }
        }

        return $next($request);
    }
}
