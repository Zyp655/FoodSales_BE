<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; 

class CartController extends Controller
{
    public function getCart(Request $request)
    {
        $userId = $request->user()->id; 

        $cartItems = Cart::where('user_id', $userId)
            ->with(['product' => function($query) {
                $query->with('seller:id,name'); 
            }])
            ->get();
            
        if ($cartItems->isEmpty()) {
            return response()->json(['success' => 1, 'message' => 'Cart is empty.', 'data' => []]);
        }

        $groupedCart = $cartItems->groupBy('product.seller_id')->map(function ($items, $sellerId) {
            $totalAmount = $items->sum(function($item) {
                return $item->quantity * $item->product->price_per_kg; 
            });
            
            return [
                'seller_id' => $sellerId,
                'seller_name' => $items->first()->product->seller->name ?? 'Unknown Seller',
                'items' => $items->makeHidden(['user_id', 'product_id']), 
                'total_amount_by_seller' => round($totalAmount, 2),
            ];
        })->values()->toArray();


        return response()->json(['success' => 1, 'data' => $groupedCart]);
    }

    public function addToCart(Request $request)
    {
        $userId = $request->user()->id; 
        
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:product,id',
            'quantity' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $productId = $request->product_id;
        $quantity = $request->quantity;

        $cartItem = Cart::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $productId], 
            ['quantity' => DB::raw('quantity + ' . $quantity)] 
        );
        
        return response()->json([
            'success' => 1, 
            'message' => 'Product added to cart or quantity updated successfully.',
            'item' => $cartItem
        ], 200);
    }
    
    public function updateCartItem(Request $request, $productId)
    {
        $userId = $request->user()->id; 
        
        $validator = Validator::make(array_merge($request->all(), ['product_id' => $productId]), [
            'product_id' => 'required|integer|exists:product,id',
            'quantity' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error.', 'errors' => $validator->errors()], 422);
        }
        
        $quantity = $request->quantity;
        
        $cartItem = Cart::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
            
        if (!$cartItem) {
            return response()->json(['success' => 0, 'message' => 'Item not found in cart.'], 404);
        }

        if ($quantity == 0) {
            $cartItem->delete();
            return response()->json(['success' => 1, 'message' => 'Item removed from cart successfully.']);
            
        } else {
            $cartItem->quantity = $quantity;
            $cartItem->save();
            return response()->json(['success' => 1, 'message' => 'Cart quantity updated successfully.']);
        }
    }
    
    public function clearCart(Request $request)
    {
        $userId = $request->user()->id;
        
        $deleted = Cart::where('user_id', $userId)->delete();
        
        return response()->json(['success' => 1, 'message' => "Successfully cleared {$deleted} items from cart."]);
    }
}
