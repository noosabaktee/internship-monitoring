<?php

namespace App\Http\Middleware;

use App\Models\MUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KmiMentorOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = MUser::find($request->session()->get('auth_user_id'));

        if (! $user || $user->txtRole !== 'Mentor') {
            abort(403, 'Akses CRUD ini hanya untuk mentor.');
        }

        return $next($request);
    }
}
