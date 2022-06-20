<?php

namespace Aurora\AAuth\Http\Middleware;




use Aurora\AAuth\Exceptions\UserHasNoAssignedRoleException;
use Closure;
use http\Client\Request;
use http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AAuth
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return Response|RedirectResponse
     * @throws UserHasNoAssignedRoleException
     */
    public function handle(Request $request, Closure $next)
    {

        /*
        if (Auth::check()) {
            $roleId = $request->session()->get('roleId');

            $userRoleOrg = DB::table('user_role_organization_node')
                ->where('user_id', '=', Auth::id())
                ->where('role_id', '=', $roleId)->first();

            if (!$userRoleOrg) {
                $firstRoleId = DB::table('user_role_organization_node')
                    ->where('user_id', '=', Auth::id())
                    ->first()?->role_id;

                if (!$firstRoleId) {
                    throw new UserHasNoAssignedRoleException();
                }

                $request->session()->put('roleId', $firstRoleId);
            }
        } else {
            // todo
            return redirect('/login');
        }
        */

        return $next($request);
    }
}
