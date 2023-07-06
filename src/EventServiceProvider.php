<?php

namespace StarsNet\Project;

use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Events\Common\Order\OrderCreated;
use App\Events\Common\Order\OrderPaid;
use SStarsNet\Project\App\Events\Common\Payment\PaidFromPinkiePay;
use App\Events\Customer\Authentication\CustomerLogin;
use App\Events\Customer\Authentication\CustomerRegistration;
use App\Listeners\Common\Checkout\ApproveOfflineCheckoutImage;
use StarsNet\Project\App\Listeners\Common\Payment\UpdateOrderCheckoutIsPaid;
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
        // Default
        // Registered::class => [
        //     SendEmailVerificationNotification::class,
        // ],

        // Authentication-related
        // CustomerRegistration::class => [
        //     SaveCustomer::class,
        // ],
        // CustomerLogin::class => [
        //     SaveCustomerLoginHistory::class
        // ],

        // Order-related
        // OrderCreated::class => [],
        PaidFromPinkiePay::class => [
            UpdateOrderCheckoutIsPaid::class,
        ],
        // OrderPaid::class => [
        //     DistributePoint::class
        // ],
        // OfflineCheckoutImageUploaded::class => [
        //     ApproveOfflineCheckoutImage::class
        // ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // $this->schedulableModels();

        // Customer::observe(CustomerObserver::class);
        // Store::observe(StoreObserver::class);
        // Warehouse::observe(WarehouseObserver::class);
    }

    private function schedulableModels()
    {
        Post::observe(PostObserver::class);
        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
    }
}
