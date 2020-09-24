<?php

namespace Jslmariano\AuthenticationOtp\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * This class describes an otp service provider.
 */
class OtpServiceProvider extends ServiceProvider
{

   /**
     * Hanldes package publish construct.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfig();

        // Merge config.
        $this->mergeConfigFrom($this->getConfigPath(), 'otp');

        $this->loadMigrationsFrom(__DIR__.'/../migrations');

        $this->publishes([
            __DIR__ . '/../resources/' => base_path('vue'),
        ]);

        /**
         * Factories are loaded on the fly in testcase to avoid composer
         * --no-dev failure on production deployment
         */
        // $this->app->make('Illuminate\Database\Eloquent\Factory')
        //             ->load(__DIR__ . '/../Factories');

    }

    public function loadConfigs()
    {
        $this->mergeConfig();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge config.
        $this->mergeConfigFrom($this->getConfigPath(), 'otp');
    }

    private function publishConfig()
    {
        $path = $this->getConfigPath();
        $this->publishes([$path => config_path('otp.php')], 'config');
    }

    private function getConfigPath()
    {
        $path = realpath(__DIR__.'/../config/otp.php');
        return $path;
    }

    private function mergeConfig()
    {
        $path = $this->getConfigPath();
        $this->mergeConfigFrom($path, 'otp');
    }
}
