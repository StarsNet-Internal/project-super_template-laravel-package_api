<?php

namespace StarsNet\Project\TripleGaga\Traits\Controllers;

use App\Constants\Model\Status;
use Illuminate\Support\Collection;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;

trait RefillInventoryRequestTrait
{
    public function filterRefillInventoryRequests(array $queryParams = []): Collection
    {
        // Exclude all deleted documents first
        $refillQuery = RefillInventoryRequest::where('status', '!=', Status::DELETED);

        // Chain all string matching query
        foreach ($queryParams as $key => $value) {
            $refillQuery = $refillQuery->where($key, $value);
        }

        return $refillQuery->with([
            'requestedAccount',
            'approvedAccount',
            'requestedWarehouse',
            'approvedWarehouse',
            'items'
        ])->get();
    }

    public function getRefillInventoryRequestFullDetails(RefillInventoryRequest $refill): RefillInventoryRequest
    {
        $refill->items = $refill->items()->get();

        $refill->requestedWarehouse;
        $refill->approvedWarehouse;

        $refill->append(['requested_account', 'approved_account']);

        return $refill;
    }
}
