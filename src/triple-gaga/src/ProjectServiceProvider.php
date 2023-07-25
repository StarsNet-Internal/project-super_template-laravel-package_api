<?php

namespace StarsNet\Project\TripleGaga;

use Illuminate\Support\ServiceProvider;

// Import the controller here directly
// use StarsNet\Project\TripleGaga\App\Http\Controllers\Customer\FakerController;

// Default Imports
use Illuminate\Support\Facades\Route;

class ProjectServiceProvider extends ServiceProvider
{
    protected $namespace = 'StarsNet\Project\TripleGaga\App\Http\Controllers';

    protected $routePrefix = 'triple-gaga';

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Routes
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
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
            $this->getCustomerRoutes();
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

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
