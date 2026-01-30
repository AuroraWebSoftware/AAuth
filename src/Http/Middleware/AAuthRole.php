<?php

namespace AuroraWebSoftware\AAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AAuthRole
{
    /**
     * @param Request $request
     * @param Closure $next
     * @param string $role
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        try {
            $aauth = app('aauth');

            if ($aauth->currentRole()?->name !== $role) {
                abort(403, 'Unauthorized role.');
            }
        } catch (\Throwable $e) {
            abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}
