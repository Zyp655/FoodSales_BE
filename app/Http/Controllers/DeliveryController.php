<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeliveryController extends Controller
{
    const DELIVERY_STATUSES = [
        Order::STATUS_PICKING_UP,
        Order::STATUS_IN_TRANSIT,
        Order::STATUS_DELIVERED,
        Order::STATUS_CANCELLED,
    ];

    public function getAvailableOrders(Request $request)
    {
        $orders = Order::where('status', Order::STATUS_READY_FOR_PICKUP)
            ->whereNull('delivery_person_id')
            ->with([
                'user:id,name,address', 
                'seller:id,name,address',
                'items', 
                'items.product' 
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['success' => 1, 'orders' => $orders]);
    }

    public function acceptOrder(Request $request, $orderId)
    {
        $driverId = Auth::id();

        try {
            return DB::transaction(function () use ($orderId, $driverId) {
                $order = Order::where('id', $orderId)
                    ->where('status', Order::STATUS_READY_FOR_PICKUP)
                    ->whereNull('delivery_person_id')
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    return response()->json(['success' => 0, 'message' => 'Order is no longer available.'], 409);
                }

                $order->delivery_person_id = $driverId;
                $order->status = Order::STATUS_PICKING_UP;
                $order->save();
                
                $order->load(['user:id,name,address', 'seller:id,name,address']);

                return response()->json([
                    'success' => 1,
                    'message' => 'Order accepted successfully.',
                    'order' => $order
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Failed to accept order.', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getDeliveryStats(Request $request)
    {
        $driverId = $request->user()->id;

        $stats = Order::where('delivery_person_id', $driverId)
            ->select(
                DB::raw('SUM(CASE WHEN status = \'' . Order::STATUS_DELIVERED . '\' THEN commission_amount ELSE 0 END) as total_earnings'),
                DB::raw('COUNT(CASE WHEN status = \'' . Order::STATUS_DELIVERED . '\' THEN 1 END) as completed_orders'),
                DB::raw('COUNT(CASE WHEN status IN (\'' . Order::STATUS_PICKING_UP . '\', \'' . Order::STATUS_IN_TRANSIT . '\') THEN 1 END) as in_progress_orders'),
                DB::raw('COUNT(CASE WHEN status = \'' . Order::STATUS_CANCELLED . '\' THEN 1 END) as failed_orders')
            )
            ->first();

        return response()->json([
            'success' => 1,
            'stats' => $stats
        ]);
    }

    public function getAssignedOrders(Request $request)
    {
        $driverId = $request->user()->id;

        $orders = Order::where('delivery_person_id', $driverId)
            ->whereIn('status', [Order::STATUS_PICKING_UP, Order::STATUS_IN_TRANSIT]) 
            ->with(['user:id,name,address', 'seller:id,name,address']) 
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => 1, 'orders' => $orders]); 
    }

    public function updateDeliveryStatus(Request $request, $orderId)
    {
        $driverId = $request->user()->id;

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', self::DELIVERY_STATUSES),
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Invalid status value provided.', 'errors' => $validator->errors()], 422);
        }

        $order = Order::where('id', $orderId)
            ->where('delivery_person_id', $driverId)
            ->first();

        if (!$order) {
            return response()->json(['success' => 0, 'message' => 'Order not found or not assigned to you.'], 404);
        }

        $oldStatus = $order->status;
        $newStatus = $request->status;

        if ($oldStatus == Order::STATUS_DELIVERED || $oldStatus == Order::STATUS_CANCELLED) {
            return response()->json(['success' => 0, 'message' => "Cannot update status. Order is already {$oldStatus}."], 400);
        }

        $order->status = $newStatus;
        $order->save();

        return response()->json([
            'success' => 1,
            'message' => "Order delivery status updated from {$oldStatus} to {$newStatus}.",
            'order' => $order
        ], 200);
    }
}