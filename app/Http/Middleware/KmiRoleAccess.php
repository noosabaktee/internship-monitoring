<?php

namespace App\Http\Middleware;

use App\Models\MUser;
use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KmiRoleAccess
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = MUser::with('intern')->find($request->session()->get('auth_user_id'));

        if (! $user || ! RoleAccess::can($user, $ability)) {
            abort(403, 'Akses halaman ini tidak tersedia untuk role Anda.');
        }

        return $next($request);
    }
}
