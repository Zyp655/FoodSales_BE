<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeliveryController extends Controller
{
    
    const DELIVERY_STATUSES = [
        'Picking Up',
        'In Transit', 
        'Delivered',  
        'Delivery Failed', 
    ];

    public function getAssignedOrders(Request $request)
    {
        $driverId = $request->user()->id;
        
        $orders = Order::where('delivery_person_id', $driverId)
            ->with('user:id,name,address')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['success' => 1, 'data' => $orders]);
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
        
        $order->status = $newStatus;
        $order->save();

        return response()->json([
            'success' => 1, 
            'message' => "Order delivery status updated from {$oldStatus} to {$newStatus}.",
            'order_id' => $order->id,
            'new_status' => $newStatus
        ], 200);
    }
}