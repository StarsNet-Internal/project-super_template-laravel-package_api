<?php

namespace StarsNet\Project\EnjoyFace\App\Traits\Controller;

// Default

use App\Constants\Model\ProductVariantDiscountType;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait ProjectStoreTrait
{
    private function vincentyGreatCircleDistance(
        $latitudeFrom,
        $longitudeFrom,
        $latitudeTo,
        $longitudeTo,
        $earthRadius = 6371
    ) {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }

    private function appendStoreAttributes($store, $reviews, $wishlistItems, $latitude, $longitude)
    {
        $storeReviews = $reviews->filter(function ($review) use ($store) {
            return $review['store_id'] === $store['_id'];
        });

        $store['rating'] = round($storeReviews->avg('rating') ?? 0, 1);
        $store['review_count'] = $storeReviews->count() ?? 0;

        $store['distance'] = round($this->vincentyGreatCircleDistance(
            $latitude,
            $longitude,
            $store['location']['latitude'],
            $store['location']['longitude']
        ), 1);

        $mtr = array_filter($store['categories']->toArray(), function ($category) {
            return $category['store_category_type'] === 'MTR';
        });
        $store['mtr'] = reset($mtr);

        $store['is_orderable'] = $store['quota'] > count($store['orders']);
        $store['remaining_quota'] = max(0, $store['quota'] - count($store['orders']));
        $store['is_liked'] = array_search($store['_id'], $wishlistItems) !== false ? true : false;

        unset($store['category_ids'], $store['opening_hours'], $store['quota'], $store['categories'], $store['orders']);
    }
}
