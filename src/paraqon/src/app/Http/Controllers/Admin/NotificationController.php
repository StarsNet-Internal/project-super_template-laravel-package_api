<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Constants\Model\Status;
use StarsNet\Project\Paraqon\App\Models\Notification;

class NotificationController extends Controller
{
    public function getAllNotifications(Request $request)
    {
        // Extract attributes from request
        $queryParams = $request->query();

        // Get Notifications
        $notificationQuery = Notification::where('type', 'staff')
            ->where('status', '!=', Status::DELETED);

        foreach ($queryParams as $key => $value) {
            if (in_array($key, ['per_page', 'page', 'sort_by', 'sort_order'])) {
                continue;
            }

            $notificationQuery->where($key, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }

        $notifications = $notificationQuery->latest()
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

        if (!is_null($notificationID)) {
            $notificationQuery = Notification::where('type', 'staff')
                ->where('_id', $notificationID);

            $readNotificationIDs = $notificationQuery->pluck('_id')->all();
            $updatedCount = $notificationQuery->update(['is_read' => true]);
        } else if (!is_null($path)) {
            $notificationQuery = Notification::where('type', 'staff')
                ->where('path', $path);

            $readNotificationIDs = $notificationQuery->pluck('_id')->all();
            $updatedCount = $notificationQuery->update(['is_read' => true]);
        } else {
            return response()->json([
                'message' => 'Invalid input type',
            ], 404);
        }

        // Get Notifications unread count
        $unreadNotificationCount = Notification::where('type', 'staff')
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

        // Get auth user info
        $account = $this->account();

        // Find Notification
        $notification = Notification::find($notificationID);

        if (is_null($notification)) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        if ($notification->type != 'staff') {
            return response()->json([
                'message' => 'This Notification does not belong to a staff',
            ], 404);
        }

        // Delete Notification
        $updatedCount = $notification->update([
            'status' => Status::ACTIVE,
            'deleted_at' => $notification->freshTimestamp()
        ]);

        // Get Notifications unread count
        $unreadNotificationCount = Notification::where('type', 'staff')
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
