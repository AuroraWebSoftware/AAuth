<?php

namespace AuroraWebSoftware\AAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AAuthPermission
{
    public function handle(Request $request, Closure $next, string $permission, mixed ...$parameters): Response
    {
        try {
            $aauth = app('aauth');

            if (! $aauth->can($permission, ...$parameters)) {
                abort(403, 'Unauthorized action.');
            }
        } catch (\Throwable $e) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
