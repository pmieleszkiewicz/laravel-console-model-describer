<?php

declare(strict_types=1);

namespace PMieleszkiewicz\ModelDescriber;

use Illuminate\Support\ServiceProvider;
use PMieleszkiewicz\ModelDescriber\Console\ModelFullDescriber;

class ModelDescriberServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/model-describer.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('model-describer.php');
        } else {
            $publishPath = base_path('config/model-describer.php');
        }
        $this->publishes([$configPath => $publishPath], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands(
                ModelFullDescriber::class
            );
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/model-describer.php';
        $this->mergeConfigFrom($configPath, 'model-describer');
    }
}