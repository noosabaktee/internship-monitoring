<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization');

        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Bearer token diperlukan.',
                'errors' => ['authentication' => ['Kirim header Authorization: Bearer <token>.']],
            ], 401);
        }

        $token = ApiToken::with('user')
            ->where('token_hash', hash('sha256', $matches[1]))
            ->first();

        if (! $token || ! $token->isUsable()) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid, sudah dicabut, kedaluwarsa, atau akun tidak aktif.',
            ], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('kmi_api_user', $token->user);
        $request->attributes->set('kmi_api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
