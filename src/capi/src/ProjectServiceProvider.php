<?php

namespace StarsNet\Project\Capi;

use Illuminate\Support\ServiceProvider;

// Import the controller here directly
// use StarsNet\Project\Capi\App\Http\Controllers\Customer\FakerController;

// Default Imports
use Illuminate\Support\Facades\Route;

use StarsNet\Project\Capi\App\Http\Middleware\Pagination;

class ProjectServiceProvider extends ServiceProvider
{
    protected $namespace = 'StarsNet\Project\Capi\App\Http\Controllers';

    protected $routePrefix = 'capi';

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Config
        // $this->mergeConfigFrom(__DIR__ . '/config/database.php', 'contactus_database');

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Migrations
        // $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function loadRoutesFrom($path): void
    {
        // Define route attributes
        $routeAttributes = [
            'middleware' => 'api',
            'namespace' => $this->namespace,
            'prefix' => 'api',
        ];

        // Define routes to be loaded from
        $getRoutesCallback = function ($router) {
            $this->getAdminRoutes();
            // $this->getCommandRoutes();
            $this->getCustomerRoutes();
            $this->getInternalRoutes();
        };

        // Load all required routes
        Route::group($routeAttributes, $getRoutesCallback);

        return;
    }

    private function getAdminRoutes()
    {
        // Define path where the routes are stored at
        $path = __DIR__ . '/routes/api/admin.php';

        // Define route attributes
        $routeAttributes = [
            'prefix' => 'admin' . '/' . $this->routePrefix,
            'namespace' => 'Admin'
        ];

        // Include routes to be loaded from
        $requireRoutes = function () use ($path) {
            require $path;
        };

        // Define routes to be loaded from
        $routes = Route::group($routeAttributes, $requireRoutes);

        return $routes;
    }

    private function getCommandRoutes()
    {
        $path = __DIR__ . '/routes/api/command.php';

        // Define route attributes
        $routeAttributes = [
            'prefix' => 'command' . '/' . $this->routePrefix,
            'namespace' => 'Command'
        ];

        // Include routes to be loaded from
        $requireRoutes = function () use ($path) {
            require $path;
        };

        // Define routes to be loaded from
        $routes = Route::group($routeAttributes, $requireRoutes);

        return $routes;
    }

    private function getCustomerRoutes()
    {
        $path = __DIR__ . '/routes/api/customer.php';

        // Define route attributes
        $routeAttributes = [
            'prefix' => 'customer' . '/' . $this->routePrefix,
            'namespace' => 'Customer'
        ];

        // Include routes to be loaded from
        $requireRoutes = function () use ($path) {
            require $path;
        };

        // Define routes to be loaded from
        $routes = Route::group($routeAttributes, $requireRoutes);

        return $routes;
    }

    private function getInternalRoutes()
    {
        $path = __DIR__ . '/routes/api/internal.php';

        // Define route attributes
        $routeAttributes = [
            'prefix' => 'internal' . '/' . $this->routePrefix,
            'namespace' => 'Internal'
        ];

        // Include routes to be loaded from
        $requireRoutes = function () use ($path) {
            require $path;
        };

        // Define routes to be loaded from
        $routes = Route::group($routeAttributes, $requireRoutes);

        return $routes;
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->app->make(FakerController::class);
        $this->registerMiddleware();
    }

    protected $routeMiddleware = [
        'capi_pagination' => Pagination::class,
    ];

    protected function registerMiddleware()
    {
        $router = $this->app['router'];
        foreach ($this->routeMiddleware as $key => $middleware) {
            $router->aliasMiddleware($key, $middleware);
        }
    }
}
