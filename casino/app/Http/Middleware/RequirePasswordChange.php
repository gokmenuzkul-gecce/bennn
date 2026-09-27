<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user === null || !(bool) $user->must_change_password) {
            return $next($request);
        }

        $allowed = [
            'liteback.profile.password',
            'liteback.profile.password.update',
            'frontend.auth.logout',
        ];
        if ($request->route() && in_array($request->route()->getName(), $allowed, true)) {
            return $next($request);
        }

        $target = route('liteback.profile.password');
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Change the temporary administrator password before continuing.',
                'redirect' => $target,
            ], 409);
        }

        return redirect($target)->with('warning', 'Change the temporary administrator password before continuing.');
    }
}
