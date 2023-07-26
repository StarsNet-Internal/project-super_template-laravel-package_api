<?php

namespace StarsNet\Project\Commads\App\Traits\Controller;

// Default

use App\Constants\Model\ProductVariantDiscountType;
use App\Models\DiscountTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use StarsNet\Project\Commads\App\Models\CustomOrderImage;
use StarsNet\Project\Commads\App\Models\CustomStoreQuote;
use App\Traits\Utils\RoundingTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait OrderTrait
{
    private function getQuoteDetails($order)
    {
        $orderId = $order['_id'];

        $quote = CustomStoreQuote::where('quote_order_id', $orderId)
            ->orWhere('purchase_order_id', $orderId)
            ->first();

        if (!is_null($quote)) {
            $images = CustomOrderImage::where('order_id', $quote->quote_order_id)
                ->orWhere('order_id', $quote->purchase_order_id)
                ->latest()
                ->first();
            $quote['is_paid'] = $quote['purchase_order_id'] ? Order::find($quote['purchase_order_id'])['is_paid'] : false;
        } else {
            $images = CustomOrderImage::where('order_id', $orderId)
                ->latest()
                ->first();
        }

        return [
            'quote' => $quote ? $quote->toArray() : $quote,
            'custom_order_images' => $images
        ];
    }
}
