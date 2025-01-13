<?php

namespace StarsNet\Project\Videocom\App\Http\Controllers\Admin;

use App\Constants\Model\CheckoutApprovalStatus;
use App\Constants\Model\CheckoutType;
use App\Constants\Model\ReplyStatus;
use App\Constants\Model\ShipmentDeliveryStatus;
use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Order;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Traits\Utils\RoundingTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use StarsNet\Project\Videocom\App\Models\AuctionLot;
use StarsNet\Project\Videocom\App\Models\AuctionRegistrationRequest;
use StarsNet\Project\Videocom\App\Models\AuctionRequest;
use StarsNet\Project\Videocom\App\Models\Bid;
use StarsNet\Project\Videocom\App\Models\ConsignmentRequest;
use StarsNet\Project\Videocom\App\Models\Deposit;
use StarsNet\Project\Videocom\App\Models\PassedAuctionRecord;
use StarsNet\Project\Videocom\App\Models\LiveBiddingEvent;

use StarsNet\Project\Videocom\App\Http\Controllers\Admin\AuctionLotController as AdminAuctionLotController;
use StarsNet\Project\Videocom\App\Http\Controllers\Customer\AuctionLotController as CustomerAuctionLotController;

class ServiceController extends Controller
{
    use RoundingTrait;

    public function paymentCallback(Request $request)
    {
        return 'OK';
    }
}
