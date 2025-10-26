<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

        $cartItems = Cart::where('user_id', $userId)
            ->whereHas('product', function($query) use ($request) {
                $query->where('seller_id', $request->seller_id);
            })
            ->with('product:id,price_per_kg,seller_id') 
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['success' => 0, 'message' => 'No items found in cart for this seller or cart is empty.'], 400);
        }

        try {
            DB::beginTransaction();

            $order = Order::create([
                'user_id' => $userId,
                'seller_id' => $request->seller_id,
                'total_amount' => $request->total_amount,
                'delivery_address' => $request->delivery_address,
                'status' => 'Pending',
            ]);
            
            $orderItemsData = [];
            $cartItemIdsToDelete = [];

            foreach ($cartItems as $item) {
                $orderItemsData[] = [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price_at_purchase' => $item->product->price_per_kg, 
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $cartItemIdsToDelete[] = $item->id;
            }

            OrderItem::insert($orderItemsData);

            Cart::whereIn('id', $cartItemIdsToDelete)->delete();

            DB::commit();
            
            return response()->json([
                'success' => 1, 
                'message' => 'Order created and items moved from cart successfully.', 
                'order_id' => $order->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => 0, 'message' => 'Failed to create order. Transaction error.', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getOrdersByUser(Request $request)
{
    $userId = $request->user()->id;

    $ordersList = Order::where('user_id', $userId)
                          ->with([
                              'seller:id,name',
                              'deliveryPerson:id,name,email,role' 
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
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Pending,Processing,Shipping,Delivered,Cancelled',
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

        return response()->json([
            'success' => 1, 
            'message' => "Order status updated from {$oldStatus} to {$newStatus}.",
            'order_id' => $order->id,
            'new_status' => $newStatus
        ], 200);
    }
}