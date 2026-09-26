<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\JWTHelper;

class EnsureComproAuthenticated
{
    /**
     * Guard for compro (public website) routes that require a logged-in
     * member. Auth here is session-based (unlike the mobile app's JWT
     * bearer header), but the identity itself is still the same JWT --
     * it's just stored server-side in the session instead of sent per
     * request. See ComproController::loginSubmit().
     */
    public function handle(Request $request, Closure $next)
    {
        $token = session('jwt_token');
        $validation = $token ? JWTHelper::validateToken($token) : ['valid' => false];

        if (!$validation['valid']) {
            session()->forget('jwt_token');
            session(['compro_intended_url' => $request->fullUrl()]);
            return redirect()->route('compro.login')
                ->with('info', 'Silakan masuk terlebih dahulu untuk melanjutkan pendaftaran.');
        }

        return $next($request);
    }
}
