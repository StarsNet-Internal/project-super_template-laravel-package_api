<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

use App\Constants\Model\ReplyStatus;
use App\Constants\Model\Status;
use App\Constants\Model\StoreType;

use App\Models\ProductVariant;
use App\Models\Store;
use Carbon\Carbon;

use StarsNet\Project\Paraqon\App\Models\AuctionLot;
use StarsNet\Project\Paraqon\App\Models\AuctionRequest;
use StarsNet\Project\Paraqon\App\Models\BidHistory;

use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\Notification;

class NotificationController extends Controller
{
    public function getAllNotifications(Request $request)
    {
        // Get auth user info
        $customer = $this->customer();

        // Get Notifications
        $notifications = Notification::where('account_id', $customer->_id)
            ->latest()
            ->get();

        return $notifications;
    }

    public function markNotificationsAsRead(Request $request)
    {
        // Extract attributes from request
        $notificationID = $request->input('id');
        $path = $request->input('path');

        // Get auth user info
        $account = $this->account();

        // Metadata for dev purpose
        $updatedCount = 0;
        $readNotificationIDs = [];

        // Update Notifications as read
        if (!is_null($notificationID)) {
            $notificationQuery = Notification::where('account_id', $account->_id)
                ->where('_id', $notificationID);

            $readNotificationIDs = $notificationQuery->pluck('_id')->all();
            $updatedCount = $notificationQuery->update(['is_read' => true]);
        } else if (!is_null($path)) {
            $notificationQuery = Notification::where('account_id', $account->_id)
                ->where('path', $path);

            $readNotificationIDs = $notificationQuery->pluck('_id')->all();
            $updatedCount = $notificationQuery->update(['is_read' => true]);
        } else {
            return response()->json([
                'message' => 'Invalid input type',
            ], 404);
        }

        // Get Notifications unread count
        $unreadNotificationCount = Notification::where('account_id', $account->_id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message' => 'Notification updated is_read as true',
            'data' => [
                'account_id' => $account->_id,
                'read_notification_count' => $updatedCount,
                'read_notification_ids' => $readNotificationIDs,
                'unread_notification_count' => $unreadNotificationCount,
            ]
        ], 200);
    }

    public function deleteNotification(Request $request)
    {
        // Extract attributes from request
        $notificationID = $request->route('id');

        // Find Notification
        $notification = Notification::find($notificationID);

        // Get auth user info
        $account = $this->account();

        if (is_null($notification)) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        if ($notification->account_id != $account->_id) {
            return response()->json([
                'message' => 'This Notification does not belong to this account_id',
            ], 404);
        }

        // Delete Notification
        $updatedCount = $notification->update([
            'status' => Status::ACTIVE,
            'deleted_at' => $notification->freshTimestamp()
        ]);

        // Get Notifications unread count
        $unreadNotificationCount = Notification::where('account_id', $account->_id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message' => 'Notification deleted',
            '_id' => $notification->id,
            'data' => [
                'account_id' => $account->_id,
                'read_notification_count' => $updatedCount,
                'read_notification_ids' => [$notification->id],
                'unread_notification_count' => $unreadNotificationCount,
            ]
        ], 200);
    }
}
