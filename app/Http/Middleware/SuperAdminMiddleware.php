<?php
namespace App\Http\Middleware;

use Closure;

class SuperAdminMiddleware
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_admin, 403, 'Superadmin only');
        return $next($request);
    }
}
    