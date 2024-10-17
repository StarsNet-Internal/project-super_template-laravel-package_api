<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

use App\Constants\Model\ShipmentDeliveryStatus;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use App\Models\Store;
use App\Models\Configuration;
use App\Models\Order;
use App\Models\ShoppingCartItem;
use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\Deposit;
use StarsNet\Project\Paraqon\App\Models\ProductStorageRecord;

use function PHPSTORM_META\map;

class TestingController extends Controller
{
    public function healthCheck()
    {
        $deposit = Deposit::find('670dee20cabe9346e20b9291');
        return $deposit->online['payment_intent_id'];

        return response()->json([
            'message' => 'OK from package/paraqon'
        ], 200);
    }

    public function createOrder()
    {
        $orderAttributes = [
            'store_id' => '670397c71d9c75ed1d0ccc32',
            'customer_id' => '6704a49e356a8d492c0d9bf5',
            'cashier_id' => null,
            'current_status' => 'submitted',
            'payment_method' => 'OFFLINE',
            'transaction_method' => 'CREDIT_CARD',
            'calculations' => [
                'currency' => 'HKD',
                'price' => [
                    'subtotal' => '99999.00',
                    'total' => '99999.00'
                ],
                'price_discount' => [
                    'local' => '0.00',
                    'global' => '0.00'
                ],
                'point' => [
                    'subtotal' => '0.00',
                    'total' => '0.00'
                ],
                'service_charge' => '0.00',
                'storage_fee' => '0.00'
            ],
            'amount_received' => null,
            'change' => 0,
            'delivery_info' => [
                'country_code' => 'HK',
                'method' => 'FACE_TO_FACE_PICKUP',
                'courier_id' => null,
                'warehouse_id' => null
            ],
            'delivery_details' => [
                'recipient_name' => null,
                'email' => null,
                'area_code' => null,
                'phone' => null,
                'address' => null,
                'remarks' => null
            ],
            'shipping_details' => [
                'first_name' => "Steven",
                'last_name' => "Lam",
                'country' => "Hong Kong",
                'city' => "Hong Kong",
                'address_line_1' => "Flat A, 25/F, 148 Electric Rd North Point",
                'address_line_2' => "Eastern District",
                'building' => 'Pacific Plaza',
                'postal_code' => '12345',
                'company_name' => 'StarsNet (HK) Limited',
                'email' => "stevenlam1234567@gmail.com",
                'area_code' => "852",
                'phone' => "12345678",
                'remarks' => null,
                'method' => 'DHL',
            ],
            'documents' => [
                'receipt' => null,
                'invoice' => null
            ],
            'is_paid' => false,
            'is_voucher_applied' => false,
            'has_reviews' => false,
            'has_refund_requests' => false,
            'is_system' => true,
            'auction_type' => 'ONLINE'
        ];

        $order = Order::create($orderAttributes);
        $itemAttributes = [
            [
                'product_id' => '66e29c4946e95ecbfa0f6be6',
                'product_variant_id' => '66e29c4aee180d2bf109f3ca',
                'qty' => 1,
                'is_checkout' => false,
                'is_refundable' => false,
                'local_discount_type' => null,
                'global_discount' => null,
                'product_title' => [
                    'en' => 'Bottle A',
                    'zh' => 'Bottle A',
                    'cn' => 'Bottle A'
                ],
                'product_variant_title' => [
                    'en' => null,
                    'zh' => null,
                    'cn' => null,
                ],
                'image' => 'https://starsnet-development.oss-cn-hongkong.aliyuncs.com/jpg/b1e9edc1-799a-4cd7-a366-4947aa304a90.jpg',
                'sku' => null,
                'barcode' => null,
                'original_price_per_unit' => '0.00',
                'discounted_price_per_unit' => '0',
                'original_subtotal_price' => '0.00',
                'subtotal_price' => '0.00',
                'original_point_per_unit' => '0.00',
                'discounted_point_per_unit' => '0.00',
                'original_subtotal_point' => '0.00',
                'subtotal_point' => '0.00',
                'winning_bid' => 30000.00,
            ],
            [
                'product_id' => '66e29c49450bbb287309d816',
                'product_variant_id' => '66e29c4a2c55a3c7950d7644',
                'qty' => 1,
                'is_checkout' => false,
                'is_refundable' => false,
                'local_discount_type' => null,
                'global_discount' => null,
                'product_title' => [
                    'en' => 'Bottle B',
                    'zh' => 'Bottle B',
                    'cn' => 'Bottle B'
                ],
                'product_variant_title' => [
                    'en' => null,
                    'zh' => null,
                    'cn' => null,
                ],
                'image' => 'https://starsnet-development.oss-cn-hongkong.aliyuncs.com/jpg/b1e9edc1-799a-4cd7-a366-4947aa304a90.jpg',
                'sku' => null,
                'barcode' => null,
                'original_price_per_unit' => '0.00',
                'discounted_price_per_unit' => '0',
                'original_subtotal_price' => '0.00',
                'subtotal_price' => '0.00',
                'original_point_per_unit' => '0.00',
                'discounted_point_per_unit' => '0.00',
                'original_subtotal_point' => '0.00',
                'subtotal_point' => '0.00',
                'winning_bid' => 16500.00,
            ],
            [
                'product_id' => '66e29c49a635e56539052512',
                'product_variant_id' => '66e29c4a46e95ecbfa0f6be7',
                'qty' => 1,
                'is_checkout' => false,
                'is_refundable' => false,
                'local_discount_type' => null,
                'global_discount' => null,
                'product_title' => [
                    'en' => 'Bottle C',
                    'zh' => 'Bottle C',
                    'cn' => 'Bottle C'
                ],
                'product_variant_title' => [
                    'en' => null,
                    'zh' => null,
                    'cn' => null,
                ],
                'image' => 'https://starsnet-development.oss-cn-hongkong.aliyuncs.com/jpg/b1e9edc1-799a-4cd7-a366-4947aa304a90.jpg',
                'sku' => null,
                'barcode' => null,
                'original_price_per_unit' => '0.00',
                'discounted_price_per_unit' => '0',
                'original_subtotal_price' => '0.00',
                'subtotal_price' => '0.00',
                'original_point_per_unit' => '0.00',
                'discounted_point_per_unit' => '0.00',
                'original_subtotal_point' => '0.00',
                'subtotal_point' => '0.00',
                'winning_bid' => 1650.00,
            ]
        ];

        foreach ($itemAttributes as $item) {
            $order->shoppingCartItems()->create($item);
        }
        $order->updateStatus(ShipmentDeliveryStatus::SUBMITTED);

        // Create ShoppingCart
        $shoppingCartItems = [
            [
                'store_id' => '670397c71d9c75ed1d0ccc32',
                'customer_id' => '6704a49e356a8d492c0d9bf5',
                'product_id' => '66e29c4946e95ecbfa0f6be6',
                'product_variant_id' => '66e29c4aee180d2bf109f3ca',
                'qty' => 1,
                'winning_bid' => 30000.00
            ],
            [
                'store_id' => '670397c71d9c75ed1d0ccc32',
                'customer_id' => '6704a49e356a8d492c0d9bf5',
                'product_id' => '66e29c49450bbb287309d816',
                'product_variant_id' => '66e29c4a2c55a3c7950d7644',
                'qty' => 1,
                'winning_bid' => 16500.00
            ],
            [
                'store_id' => '670397c71d9c75ed1d0ccc32',
                'customer_id' => '6704a49e356a8d492c0d9bf5',
                'product_id' => '66e29c49a635e56539052512',
                'product_variant_id' => '66e29c4a46e95ecbfa0f6be7',
                'qty' => 1,
                'winning_bid' => 1650.00
            ]
        ];

        foreach ($shoppingCartItems as $item) {
            ShoppingCartItem::create($item);
        }

        return response()->json([
            'message' => 'Created Order',
            'order_id' => $order->_id
        ], 200);
    }
}
