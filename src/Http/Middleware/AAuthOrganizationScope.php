<?php

namespace AuroraWebSoftware\AAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AAuthOrganizationScope
{
    /**
     * @param Request $request
     * @param Closure $next
     * @param int|null $organizationScopeId
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?int $organizationScopeId = null): Response
    {
        try {
            $aauth = app('aauth');
            $activeRole = $aauth->currentRole();

            if ($organizationScopeId !== null) {
                if ($activeRole?->organization_scope_id !== $organizationScopeId) {
                    abort(403, 'Unauthorized organization scope.');
                }
            }
        } catch (\Throwable $e) {
            abort(403, 'Unauthorized organization scope.');
        }

        return $next($request);
    }
}
