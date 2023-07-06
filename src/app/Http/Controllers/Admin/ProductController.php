<?php

namespace StarsNet\Project\App\Http\Controllers\Admin;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Store;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\ProductVariantDiscount;
use App\Models\ProductVariantOption;
use StarsNet\Project\App\Models\AccountProduct;
use StarsNet\Project\App\Models\Deal;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\ReviewTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use App\Traits\Utils\Flattenable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Admin\ProductController as AdminProductController;
use StarsNet\Project\App\Traits\Controller\ProjectAccountTrait;

class ProductController extends AdminProductController
{
    use ProjectAccountTrait;

    public function getAllProducts(Request $request)
    {
        $account = $this->account();

        $products = parent::getAllProducts($request);

        if ((bool) $this->checkIfAccountIsSuperAdminOrAdmin($account)) {
            return $products;
        }

        $access = AccountProduct::where('account_id', $account->_id)->get();
        if ($access) {
            $ids = $access->pluck('product_id')->all();

            return array_filter($products->toArray(), function ($product) use ($ids) {
                return in_array($product['_id'], $ids);
            });
        }
        return new Collection();
    }

    public function createProduct(Request $request)
    {
        $account = $this->account();

        $product = parent::createProduct($request);

        $access = AccountProduct::create([]);
        $access->associateAccount($account);
        $access->associateProduct($product);

        return $product;
    }

    public function editProductAndDiscountDetails(Request $request)
    {
        $productID = $request->route('id');

        $deals = Deal::where('product_id', $productID)->get();

        foreach ($deals as $deal) {
            if ($deal->isDealOngoing()) {
                return response()->json([
                    'message' => 'Product associated with Active Deal is not editable'
                ], 400);
            }
        }

        $product = parent::editProductAndDiscountDetails($request);

        return $product;
    }
}
