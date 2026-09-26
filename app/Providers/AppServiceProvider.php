<?php

namespace App\Providers;

use App\Helpers\JWTHelper;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Decode the compro session's JWT once per request so the navbar can
        // greet the logged-in member by name without every controller having
        // to pass it explicitly.
        View::composer('compro.layout', function ($view) {
            $nama = null;
            $token = session('jwt_token');
            if ($token) {
                $validation = JWTHelper::validateToken($token);
                if ($validation['valid'] ?? false) {
                    $nama = $validation['data']['nama'] ?? null;
                }
            }
            $view->with('comproUserName', $nama);
        });
    }
}
