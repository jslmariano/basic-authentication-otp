<?php

namespace Jslmariano\AuthenticationOtp\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

// use Jslmariano\AuthenticationOtp\Http\Middleware\JsonMiddleware;

/**
 * This class describes an otp routes provider.
 */
class OtpRoutesProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'Jslmariano\AuthenticationOtp\Http\Controllers';

    /**
     * Also boots parent ServiceProvider
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * invoked from parent ServiceProvider
     */
    public function map()
    {
        $this->mapApiRoutes();

    }

    /**
     * Register all routes from `/../Routes/api.php`
     */
    protected function mapApiRoutes()
    {
        $middlewares = array();
        // $middlewares[] = JsonMiddleware::class;
        // $middlewares[] = 'auth:api';
        // $middlewares[] = 'api';
        Route::prefix('api')
            ->middleware($middlewares)
            ->namespace($this->namespace)
            ->group(__DIR__ . '/../Routes/api.php');
    }
}
