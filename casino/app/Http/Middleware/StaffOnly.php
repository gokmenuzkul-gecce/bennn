<?php
namespace VanguardLTE\Http\Middleware
{
    /**
     * Restricts the operator console (/liteback) to staff accounts.
     *
     * Roles: 1 = player, 2 = cashier, 3 = manager, 4 = distributor,
     * 5 = agent, 6 = admin. Players must never reach the console, which
     * exposes user management, balances and payment settings.
     */
    class StaffOnly
    {
        public function handle($request, \Closure $next)
        {
            if( !auth()->check() )
            {
                return $next($request);
            }
            if( !in_array((int) auth()->user()->role_id, [2, 3, 4, 5, 6], true) )
            {
                abort(403);
            }
            return $next($request);
        }
    }

}
