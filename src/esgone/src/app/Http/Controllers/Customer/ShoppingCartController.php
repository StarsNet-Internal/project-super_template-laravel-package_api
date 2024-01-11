<?php

namespace StarsNet\Project\Esgone\App\Http\Controllers\Customer;

use Illuminate\Http\Request;
use App\Http\Controllers\Customer\ShoppingCartController as CustomerShoppingCartController;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;

class ShoppingCartController extends CustomerShoppingCartController
{
    public function getAll(Request $request)
    {
        // Fetch data from previous function
        $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

        // Append ProductVariant per cart_item
        foreach ($data['cart_items'] as $key => $item) {
            $productVariantId = $item['product_variant_id'];
            $variant = ProductVariant::find($productVariantId);

            $data['cart_items'][$key]['variant'] = $variant;
        }

        // Return modified data
        return response()->json($data);
    }

    public function getAllHere(Request $request)
    {
        // Fetch data from previous function
        $data = json_decode(json_encode(parent::getAll($request)), true)['original'];

        // Append ProductVariant per cart_item
        foreach ($data['cart_items'] as $key => $item) {
            $productVariantId = $item['product_variant_id'];
        }
    }

    public function getRelatedProductsUrls(Request $request)
    {
        // Validate Request
        // $validator = Validator::make($request->all(), [
        //     'exclude_ids' => [
        //         'nullable',
        //         'array'
        //     ],
        //     'exclude_ids.*' => [
        //         'exists:App\Models\Product,_id'
        //     ],
        //     'items_per_page' => [
        //         'required',
        //         'integer'
        //     ]
        // ]);

        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 400);
        // }

        // Extract attributes from $request
        $excludedProductIDs = $request->input('exclude_ids', []);
        $itemsPerPage = $request->items_per_page;

        // Get authenticated User information
        $customer = $this->customer();

        // Get first valid product_id
        $cartItems = $customer->getAllCartItemsByStore($this->store);
        $addedProductIDs = $cartItems->pluck('product_id')->all();
        $productID = count($addedProductIDs) > 0 ? $addedProductIDs[0] : null;

        if (!is_null($productID)) {
            /** @var Product $product */
            $product = Product::find($productID);
            // Append to excluded Product
            $excludedProductIDs[] = $product->_id;
        }

        // Initialize a Product collector
        $products = [];

        /*
        *   Stage 1:
        *   Get Product(s) from System ProductCategory, recommended-products
        */
        $systemCategory = ProductCategory::slug('recommended-products')->first();

        if (!is_null($systemCategory)) {
            // Get Product(s)
            $recommendedProducts = $systemCategory->products()
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $recommendedProducts = $recommendedProducts->shuffle(); // randomize ordering

            // Collect data
            $products = array_merge($products, $recommendedProducts->all()); // collect Product(s)
            $excludedProductIDs = array_merge($excludedProductIDs, $recommendedProducts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 2:
        *   Get Product(s) from active, related ProductCategory(s)
        */
        if (isset($product) && !is_null($product)) {
            // Get related ProductCategory(s) by Product and within Store
            $relatedCategories = $product->categories()
                ->storeID($this->store)
                ->statusActive()
                ->get();

            $relatedCategoryIDs = $relatedCategories->pluck('_id')->all();

            // Get Product(s)
            $relatedProducts = Product::whereHas('categories', function ($query) use ($relatedCategoryIDs) {
                $query->whereIn('_id', $relatedCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $relatedProducts = $relatedProducts->shuffle(); // randomize ordering

            // Collect data
            $products = array_merge($products, $relatedProducts->all()); // collect Product(s)
            $excludedProductIDs = array_merge($excludedProductIDs, $relatedProducts->pluck('_id')->all()); // collect _id
        }

        /*
        *   Stage 3:
        *   Get Product(s) assigned to this Store's active ProductCategory(s)
        */
        // Get remaining ProductCategory(s) by Store
        if (!isset($relatedCategoryIDs)) $relatedCategoryIDs = [];
        $otherCategories = $this->store
            ->productCategories()
            ->statusActive()
            ->excludeIDs($relatedCategoryIDs)
            ->get();

        if ($otherCategories->count() > 0) {
            $otherCategoryIDs = $otherCategories->pluck('_id')->all();

            // Get Product(s)
            $otherProducts = Product::whereHas('categories', function ($query) use ($otherCategoryIDs) {
                $query->whereIn('_id', $otherCategoryIDs);
            })
                ->statusActive()
                ->excludeIDs($excludedProductIDs)
                ->get();

            // Randomize ordering
            $otherProducts = $otherProducts->shuffle();

            // Collect data
            $products = array_merge($products, $otherProducts->all());
        }

        /*
        *   Stage 4:
        *   Generate URLs
        */
        $productIDsSet = collect($products)->pluck('_id')
            ->chunk($itemsPerPage)
            ->all();

        $urls = [];
        foreach ($productIDsSet as $IDsSet) {
            $urls[] = str_replace('https', 'http', route('esgone.products.ids', [
                'store_id' => $this->store->_id,
                'ids' => $IDsSet->all()
            ]));
        }

        // Return url(s)
        return $urls;
    }
}
