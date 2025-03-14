<?php

namespace StarsNet\Project\Green360;

use Illuminate\Support\ServiceProvider;

// Import the controller here directly
// use StarsNet\Project\Green360\App\Http\Controllers\Customer\FakerController;

// Default Imports
use Illuminate\Support\Facades\Route;

class ProjectServiceProvider extends ServiceProvider
{
    protected $namespace = 'StarsNet\Project\Green360\App\Http\Controllers';

    protected $routePrefix = 'green360';

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
            $this->getCustomerRoutes();
        };

        // Load all required routes
        Route::group($routeAttributes, $getRoutesCallback);

        return;
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
    public function boot() {}
}
