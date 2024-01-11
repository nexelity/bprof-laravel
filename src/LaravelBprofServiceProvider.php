<?php

namespace Nexelity\Bprof;

use Nexelity\Bprof\BprofLib;
use Event;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;

class LaravelBprofServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // Publish a config file
        $this->publishes(
            [
                __DIR__ . '/../config/bprof.php' => config_path('bprof.php')
            ],
            'config'
        );

        // Publish commands
        $this->commands([
            Console\TrimBprofTraces::class,
            Console\TruncateBprofTraces::class,
        ]);

        // Publish migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        $configPath = __DIR__ . '/../config/bprof.php';
        $this->mergeConfigFrom($configPath, 'bprof');

        // Bind the BprofLib class
        $this->app->bind(BprofLib::class, function ($app) {
            return new BprofLib();
        });

    }
}
