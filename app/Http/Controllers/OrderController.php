<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
class OrderController extends Controller
{
    public function createOrder(Request $request)
    {
        $userId = $request->user()->id; 
        
        $validator = Validator::make($request->all(), [
            'seller_id' => 'required|integer|exists:sellers,id',
            'total_amount' => 'required|numeric|min:0',
            'delivery_address' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            $cartItems = Cart::where('user_id', $userId)
                ->whereHas('product', function($query) use ($request) {
                    $query->where('seller_id', $request->seller_id);
                })
                ->get();
            
            if ($cartItems->isEmpty()) {
                $totalCartItems = Cart::where('user_id', $userId)->count();
                $message = 'No items found in cart for this seller (ID: ' . $request->seller_id . ') or cart is empty.';
                
                if ($totalCartItems > 0) {
                    $message .= ' User has ' . $totalCartItems . ' items in cart, but none match this seller ID.';
                } else {
                    $message .= ' User cart is totally empty.';
                }
                
                DB::rollBack();
                return response()->json(['success' => 0, 'message' => $message], 400);
            }

            $order = Order::create([
                'user_id' => $userId,
                'seller_id' => $request->seller_id,
                'total_amount' => $request->total_amount,
                'delivery_address' => $request->delivery_address,
                'status' => Order::STATUS_PENDING, 
            ]);
            
            $orderItemsData = [];
            $cartItemIdsToDelete = [];

            foreach ($cartItems as $item) {
                $item->load('product:id,price_per_kg'); 
                
                if (!$item->product) {
                    continue; 
                }
                
                $priceAtPurchase = $item->product->price_per_kg;

                $orderItemsData[] = [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price_at_purchase' => $priceAtPurchase, 
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $cartItemIdsToDelete[] = $item->id;
            }

            if (empty($orderItemsData)) {
                    DB::rollBack();
                    return response()->json(['success' => 0, 'message' => 'Failed to process any cart item.', 'debug' => $cartItems->pluck('id')], 500);
            }

            OrderItem::insert($orderItemsData);

            Cart::whereIn('id', $cartItemIdsToDelete)->delete();

            DB::commit();
            
            $order->load(['user:id,name', 'seller:id,name', 'items.product']);
            
            return response()->json([
                'success' => 1, 
                'message' => 'Order created successfully.', 
                'order' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => 0, 'message' => 'Failed to create order.', 'error' => $e->getMessage()], 500);
        }
    }

    public function fixMissingCommissions(Request $request)
    {
        $ordersToFix = Order::where('status', Order::STATUS_READY_FOR_PICKUP)
                            ->whereNull('commission_amount')
                            ->get();

        if ($ordersToFix->isEmpty()) {
            return response()->json(['message' => 'No orders needed fixing.']);
        }

        $count = 0;
        foreach ($ordersToFix as $order) {
            $percent_commission_rate = 0.05;
            $km_commission_rate = 5000;
            $simulated_distance = rand(20, 150) / 10.0;
            $commission_from_total = $order->total_amount * $percent_commission_rate;
            $commission_from_distance = $simulated_distance * $km_commission_rate;
            
            $order->distance_km = $simulated_distance;
            $order->commission_amount = $commission_from_total + $commission_from_distance;
            $order->save();
            $count++;
        }

        return response()->json([
            'message' => 'Successfully fixed ' . $count . ' old orders.'
        ]);
    }
    
    public function getOrdersByUser(Request $request)
    {
        $userId = $request->user()->id; 

        $ordersList = Order::where('user_id', $userId)
                                ->with([
                                    'seller:id,name',
                                    'deliveryPerson:id,name,email,role',
                                    'items', 
                                    'items.product' 
                                    ])
                                ->orderBy('created_at', 'desc')
                                ->get();

        if ($ordersList->isEmpty()) {
            return response()->json(['success' => 1, 'message' => 'No orders found for this user.', 'orders' => []]);
        }
        
        return response()->json(['success' => 1, 'orders' => $ordersList]);
    }
    
    public function updateOrderStatus(Request $request, $orderId)
    {
        $validStatuses = [
            Order::STATUS_PENDING, 
            Order::STATUS_PROCESSING, 
            Order::STATUS_IN_TRANSIT, 
            Order::STATUS_DELIVERED, 
            Order::STATUS_CANCELLED
        ];
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', $validStatuses),
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Invalid status value provided.', 'errors' => $validator->errors()], 422);
        }

        $order = Order::find($orderId);
        
        if (!$order) {
            return response()->json(['success' => 0, 'message' => 'Order not found.'], 404);
        }
        
        $oldStatus = $order->status;
        $newStatus = $request->status;
        
        $order->status = $newStatus;
        $order->save();
        
        $order->load(['user:id,name', 'seller:id,name', 'deliveryPerson:id,name', 'items.product']);

        return response()->json([
            'success' => 1, 
            'message' => "Order status updated from {$oldStatus} to {$newStatus}.",
            'order' => $order
        ], 200);
    }
    
    public function updateSellerOrderStatus(Request $request, $orderId)
    {
        $sellerAllowedStatuses = [
            Order::STATUS_PROCESSING, 
            Order::STATUS_READY_FOR_PICKUP
        ];
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', $sellerAllowedStatuses),
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Invalid status value provided.', 'errors' => $validator->errors()], 422);
        }

        $sellerId = Auth::id();
        $newStatus = $request->status;
        
        $order = Order::where('id', $orderId)
                        ->where('seller_id', $sellerId)
                        ->first();

        if (!$order) {
            return response()->json(['success' => 0, 'message' => 'Order not found or access denied.'], 404);
        }
        
        $oldStatus = $order->status;
        
        if (!in_array($oldStatus, [Order::STATUS_PENDING, Order::STATUS_PROCESSING])) {
             return response()->json(['success' => 0, 'message' => "Cannot update status from {$oldStatus}. Order must be Pending or Processing."], 400);
        }
        
        if ($newStatus == Order::STATUS_READY_FOR_PICKUP && is_null($order->commission_amount)) {
            $percent_commission_rate = 0.05;
            $km_commission_rate = 5000;
            $simulated_distance = rand(20, 150) / 10.0;
            $commission_from_total = $order->total_amount * $percent_commission_rate;
            $commission_from_distance = $simulated_distance * $km_commission_rate;
            
            $order->distance_km = $simulated_distance;
            $order->commission_amount = $commission_from_total + $commission_from_distance;
        }

        $order->status = $newStatus;
        $order->save();

        $order->load(['user:id,name', 'seller:id,name', 'deliveryPerson:id,name', 'items.product']);

        return response()->json([
            'success' => 1,
            'message' => "Order #{$order->id} status updated from {$oldStatus} to {$newStatus}.",
            'order' => $order
        ], 200);
    }

    public function getOrdersBySeller(Request $request)
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

    public function getOrderDetails(Request $request, $orderId)
    {
        $userId = $request->user()->id;

        try {
            $order = Order::where('id', $orderId)
                        ->where('user_id', $userId) 
                        ->with([ 
                            'seller:id,name,image,address,phone',
                            'deliveryPerson:id,name,phone',
                            'items',
                            'items.product'
                        ])
                        ->firstOrFail(); 

            return response()->json(['success' => 1, 'order' => $order], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Order not found or access denied.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Server error.', 'error' => $e->getMessage()], 500);
        }
    }
}