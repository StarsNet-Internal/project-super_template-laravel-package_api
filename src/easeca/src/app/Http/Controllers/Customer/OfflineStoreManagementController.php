<?php

namespace StarsNet\Project\Easeca\App\Http\Controllers\Customer;

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
use Illuminate\Http\Request;

class OfflineStoreManagementController extends Controller
{
    public function getAllOfflineStores(Request $request)
    {
        // Get all Store(s)
        $stores = Store::statusActive()
            ->whereType(StoreType::OFFLINE)
            ->get();

        foreach ($stores as $key => $value) {
            $stores[$key]['addresses'] = array_map(function ($address) {
                return ['en' => $address['en'], 'zh' => $address['zh'], 'cn' => $address['cn']];
            }, $stores[$key]['addresses']);
        }

        // Return Store(s)
        return $stores;
    }
}
