<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\StoreDependentTrait;
use App\Traits\Controller\WishlistItemTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Customer\ProductManagementController as CustomerProductManagementController;
use Illuminate\Support\Facades\Log;

class ProductManagementController extends CustomerProductManagementController
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait,
        StoreDependentTrait;

    use Cacheable;

    /** @var Store $store */
    protected $store;

    public function __construct(Request $request)
    {
        $account = $this->account();
        Log::info($account->store_id);

        if ($account->store_id != null) {
            $this->store = $this->getStoreByValue($account->store_id);
        } else {
            $this->store = $this->getStoreByValue($request->route('store_id'));
        }
        Log::info('in package');
        Log::info($this->store);
    }
}
