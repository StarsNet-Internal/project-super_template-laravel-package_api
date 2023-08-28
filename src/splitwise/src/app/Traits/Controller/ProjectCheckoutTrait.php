<?php

namespace StarsNet\Project\Splitwise\App\Traits\Controller;

// Default

use App\Constants\Model\CheckoutType;
use App\Constants\Model\OrderDeliveryMethod;
use App\Events\Common\Checkout\OfflineCheckoutImageUploaded;
use App\Models\Checkout;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Warehouse;
use App\Traits\StarsNet\BankInSlipApprover;
use App\Traits\StarsNet\PinkiePay;

trait ProjectCheckoutTrait
{
    private function updateAsOfflineCheckoutWithoutBankInSlipApprover(Checkout $checkout, ?string $imageUrl): void
    {
        if (is_null($imageUrl)) return;

        /** @var Order $order */
        $order = $checkout->order;
        $checkout->updateOfflineImage($imageUrl);

        // Fire event
        // event(new OfflineCheckoutImageUploaded($order));
        return;
    }
}
