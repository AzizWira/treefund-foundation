<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->route('dashboard')->with('status', 'Halaman tersebut tidak tersedia untuk peran akun Anda. Anda diarahkan ke dashboard.');
            }

            abort(403);
        }

        return $next($request);
    }
}
