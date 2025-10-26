<?php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = Auth::id();

        $ordersList = Order::where('seller_id', $sellerId)
                            ->with(['user:id,name', 'deliveryPerson:id,name'])
                            ->orderBy('created_at', 'desc')
                            ->get(); 
        return response()->json(['success' => 1, 'orders' => $ordersList]);
    }

    public function updateStatus(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:preparing,ready_for_pickup',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Invalid status value provided.', 'errors' => $validator->errors()], 422);
        }

        $sellerId = Auth::id();
        $order = Order::where('id', $orderId)
                      ->where('seller_id', $sellerId)
                      ->first();

        if (!$order) {
            return response()->json(['success' => 0, 'message' => 'Order not found or access denied.'], 404);
        }

        $oldStatus = $order->status;
        $newStatus = $request->status;

        $order->status = $newStatus;
        $order->save();

        $order->load(['user:id,name', 'seller:id,name', 'deliveryPerson:id,name', 'items.product']);

        return response()->json([
            'success' => 1,
            'message' => "Order #{$order->id} status updated from {$oldStatus} to {$newStatus}.",
            'order' => $order
        ], 200);
    }
}