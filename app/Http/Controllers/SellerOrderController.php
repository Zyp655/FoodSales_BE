<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = Auth::id();

        $ordersList = Order::where('seller_id', $sellerId)
                            ->with([
                                'user:id,name', 
                                'deliveryPerson:id,name',
                                'items',
                                'items.product' 
                            ])
                            ->orderBy('created_at', 'desc')
                            ->get(); 
        return response()->json(['success' => 1, 'orders' => $ordersList]);
    }

    public function updateStatus(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Processing,ReadyForPickup', 
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

    public function getAnalytics(Request $request)
    {
        $sellerId = Auth::id();

        $totalRevenue = Order::where('seller_id', $sellerId)
                             ->where('status', Order::STATUS_DELIVERED)
                             ->sum('total_amount');

        $completedOrders = Order::where('seller_id', $sellerId)
                                ->where('status', Order::STATUS_DELIVERED)
                                ->count();

        $pendingOrders = Order::where('seller_id', $sellerId)
                              ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_READY_FOR_PICKUP])
                              ->count();
        
        $inTransitOrders = Order::where('seller_id', $sellerId)
                                ->whereIn('status', [Order::STATUS_PICKING_UP, Order::STATUS_IN_TRANSIT])
                                ->count();

        $cancelledOrders = Order::where('seller_id', $sellerId)
                                ->where('status', Order::STATUS_CANCELLED)
                                ->count();

        $topProducts = OrderItem::whereHas('order', function($query) use ($sellerId) {
                                $query->where('seller_id', $sellerId)
                                      ->where('status', Order::STATUS_DELIVERED);
                            })
                            ->with('product:id,name')
                            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                            ->groupBy('product_id')
                            ->orderBy('total_quantity', 'desc')
                            ->limit(5)
                            ->get();

        $topSellingProducts = $topProducts->map(function($item) {
            return [
                'product_name' => $item->product ? $item->product->name : 'Unknown Product',
                'total_quantity' => (int) $item->total_quantity,
            ];
        });

        return response()->json([
            'success' => 1,
            'stats' => [
                'total_revenue' => $totalRevenue,
                'completed_orders' => $completedOrders,
                'pending_orders' => $pendingOrders,
                'in_transit_orders' => $inTransitOrders,
                'cancelled_orders' => $cancelledOrders,
                'top_selling_products' => $topSellingProducts,
            ]
        ]);
    }
}