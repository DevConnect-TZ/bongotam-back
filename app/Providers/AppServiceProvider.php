<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        $this->app->booted(function (): void {
            foreach (Route::getRoutes() as $route) {
                if ($route->uri() === '_boost/browser-logs') {
                    $route->middleware(['api.auth', 'api.admin', 'trusted.origin', 'throttle:api-admin']);
                }
            }
        });

        foreach (['api' => 240, 'api-admin' => 120, 'api-auth' => 30, 'payments' => 6, 'support' => 5, 'video-views' => 30, 'web-auth' => 30, 'web-login' => 10] as $name => $attempts) {
            RateLimiter::for($name, static fn (Request $request) => Limit::perMinute($attempts)->by($request->user()?->id ?? $request->ip()));
        }
    }
}
