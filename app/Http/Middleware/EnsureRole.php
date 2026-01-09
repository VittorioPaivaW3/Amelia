<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $allowed = [];
        foreach ($roles as $roleParam) {
            foreach (explode(',', (string) $roleParam) as $role) {
                $role = trim($role);
                if ($role !== '') {
                    $allowed[$role] = true;
                }
            }
        }

        if ($allowed && ! isset($allowed[$user->role])) {
            abort(403);
        }

        return $next($request);
    }
}
