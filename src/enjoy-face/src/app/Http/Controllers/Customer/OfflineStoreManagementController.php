<?php

namespace StarsNet\Project\EnjoyFace\App\Http\Controllers\Customer;

use App\Constants\Model\DiscountTemplateType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;
use App\Http\Controllers\Controller;
use App\Models\Cashier;
use App\Models\CustomerGroup;
use App\Models\DiscountTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Warehouse;
use StarsNet\Project\EnjoyFace\App\Models\StoreCategory;
use Illuminate\Http\Request;

class OfflineStoreManagementController extends Controller
{
    public function getAllStoreCategories(Request $request)
    {
        // Get StoreCategory(s)
        /** @var Collection $categories */
        $districts = StoreCategory::whereItemType('Store')
            ->where('store_category_type', 'DISTRICT')
            ->statusActive()
            ->get();
        $ratings = StoreCategory::whereItemType('Store')
            ->where('store_category_type', 'RATING')
            ->statusActive()
            ->get();

        // Return StoreCategory(s)
        return [
            'districts' => $districts,
            'ratings' => $ratings,
        ];
    }
}
