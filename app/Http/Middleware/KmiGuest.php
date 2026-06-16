<?php

namespace App\Http\Middleware;

use App\Models\MUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KmiGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('auth_user_id');

        if ($userId && MUser::where('intUser_ID', $userId)->where('bitActive', true)->exists()) {
            return redirect()->route('dashboard.index');
        }

        return $next($request);
    }
}
