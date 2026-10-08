<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan (is_active=false) tidak boleh memakai API
 * walau tokennya masih tersimpan di perangkat.
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi pemilik bengkel.',
                'code' => 'AKUN_NONAKTIF',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
