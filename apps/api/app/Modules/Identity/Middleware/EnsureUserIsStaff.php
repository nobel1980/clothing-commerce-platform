<?php

namespace App\Modules\Identity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    /**
     * Handle an incoming request.
     * Enforces that the user is authenticated, has active status, and possesses a staff role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Account is deactivated or suspended.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$user->isStaff()) {
            return response()->json([
                'message' => 'Access denied. Authorized store staff privileges required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
