<?php

namespace StarsNet\Project\SnoreCircle\App\Http\Controllers\Customer;

use App\Constants\Model\ProductVariantDiscountType;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Hierarchy;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\ProductReview;
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

class ProductManagementController extends CustomerProductManagementController
{
    use AuthenticationTrait,
        ProductTrait,
        Sortable,
        WishlistItemTrait,
        StoreDependentTrait;

    use Cacheable;

    public function getAllReviews(Request $request)
    {
        // Extract attributes from $request
        $rating = intval($request->input('rating', '4'));
        $slug = $request->input('slug', 'from-new-to-old');

        // Get sorting attributes via slugs
        if (!is_null($slug)) {
            $sortingValue = $this->getProductSortingAttributesBySlug('review-sorting', $slug);
            switch ($sortingValue['type']) {
                case 'KEY':
                    $request['sort_by'] = $sortingValue['key'];
                    $request['sort_order'] = $sortingValue['ordering'];
                    break;
                case 'KEYWORD':
                    break;
                default:
                    break;
            }
        }

        $reviews = ProductReview::where('model_type', 'Product')
            ->where('rating', '>=', $rating)
            ->statusActive()
            ->get();

        // Return data
        return $reviews;
    }
}
