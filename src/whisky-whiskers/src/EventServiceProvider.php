<?php

namespace StarsNet\Project\WhiskyWhiskers;

use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Events\Common\Order\OrderCreated;
use App\Events\Common\Order\OrderPaid;
use StarsNet\Project\WhiskyWhiskers\App\Events\Common\Payment\PaidFromPinkiePay;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;
use StarsNet\Project\WhiskyWhiskers\App\Listeners\Common\Payment\UpdateOrderCheckoutIsPaid;
use App\Listeners\Customer\Authentication\SaveCustomer;
use App\Listeners\Customer\Authentication\SaveCustomerLoginHistory;
use App\Listeners\Customer\MembershipPoint\DistributePoint;

use App\Models\Customer;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Warehouse;

use App\Observers\CustomerObserver;
use App\Observers\PostObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use App\Observers\StoreObserver;
use App\Observers\WarehouseObserver;

// use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
// use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        PaidFromPinkiePay::class => [
            UpdateOrderCheckoutIsPaid::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    private function schedulableModels() {}
}
