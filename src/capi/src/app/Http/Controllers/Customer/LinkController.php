<?php

namespace StarsNet\Project\Capi\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Models\Store;
use StarsNet\Project\Capi\App\Models\Deal;
use StarsNet\Project\Capi\App\Models\Link;
use App\Traits\Controller\AuthenticationTrait;
use App\Traits\Controller\Cacheable;
use App\Traits\Controller\DummyDataTrait;
use App\Traits\Controller\ProductTrait;
use App\Traits\Controller\Sortable;
use App\Traits\Controller\WishlistItemTrait;
use App\Traits\StarsNet\TypeSenseSearchEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class LinkController extends Controller
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait;

    use Cacheable;

    public function getAllLinks(Request $request)
    {
        $customer = $this->customer();

        $links = Link::where('customer_id', $customer->_id)
            ->with([
                'deal' => function ($deal) {
                    $deal->select('_id', 'title');
                },
                'product' => function ($product) {
                    $product->select('_id', 'title');
                }
            ])
            ->get([]);

        return $links;
    }

    public function getDetails(Request $request)
    {
        // Extract attributes from $request
        $linkID = $request->route('link_id');

        // Get Link, then validate
        /** @var Link $link */
        $link = Link::with([
            'deal',
            'product'
        ])->find($linkID);

        if (is_null($link)) {
            return response()->json([
                'message' => 'Link not found'
            ], 404);
        }

        // Get authenticated User information, then validate
        $customer = $this->customer();

        if ($link->customer_id !== $customer->_id) {
            return response()->json([
                'message' => 'Link does not belong to this Affiliator'
            ], 401);
        }

        // Return data
        return response()->json($link);
    }

    public function createLinks(Request $request)
    {
        // Extract attributes from $request
        $dealIDs = $request->input('deal_ids', []);

        $customer = $this->customer();

        $deals = Deal::objectIDs($dealIDs)->get();

        $ids = [];

        foreach ($deals as $deal) {
            $url = env('CUSTOMER_URL') . '/shop/deal/product/' . $deal->_id . '/' . $this->titleToSlug($deal['product']['title']['zh']) . '?affiliator_id=' . $customer['_id'];

            $link = Link::create([
                'value' => $url,
                'product_id' => $deal['product_id']
            ]);
            $link->associateCustomer($customer);
            $link->associateDeal($deal);

            $ids[] = $url;
        }

        return response()->json([
            'message' => 'Created New Links successfully',
            '_ids' => $ids
        ], 200);
    }

    public function titleToSlug($title)
    {
        $regex = '/[^a-zA-Z0-9\x{4e00}-\x{9fa5}]/u';
        return preg_replace(
            [
                $regex, // replace any non-letter, non-digit, or non-Chinese character with a dash
                '/-+/u', // replace multiple dashes with a single dash
                '/^-+|-+$/u', // remove any leading or trailing dashes
            ],
            [
                '-',
                '-',
                '',
            ],
            mb_strtolower($title)
        );
    }
}
