<?php namespace Vis\Registration;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

class RegistrationServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        require __DIR__ . '/../vendor/autoload.php';

        $this->setupRoutes($this->app->router);
        $this->loadViewsFrom(realpath(__DIR__ . '/resources/views'), 'registration');
        $this->loadViewsFrom(base_path('resources/views/vis/registration'), 'registration');

        $this->publishes([
            __DIR__
            . '/published' => public_path('packages/vis/registration'),
            __DIR__ . '/config' => config_path('registration/')
        ], 'registration');

        $this->publishes([
            __DIR__
            . '/published' => public_path('packages/vis/registration')
        ], 'public');

        $this->publishes([
            __DIR__.'/resources/views' => resource_path('views/vendor/registration'),
        ], 'registration_views');
        
    }

    /**
     * Define the routes for the application.
     *
     * @param  \Illuminate\Routing\Router $router
     *
     * @return void
     */
    public function setupRoutes(Router $router)
    {
        if (!$this->app->routesAreCached()) {
            require __DIR__ . '/Http/routers.php';
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }

    public function provides()
    {
    }
}



