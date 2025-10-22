<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Seller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    
    public function listAllSellers()
    {
        $sellers = Seller::select('id', 'name', 'email', 'address', 'description', 'created_at')
                            ->orderBy('created_at', 'desc')
                            ->paginate(20);
                            
        return response()->json(['success' => 1, 'data' => $sellers], 200);
    }
    
    public function updateSellerStatus(Request $request, $sellerId)
    {
        $request->validate(['status' => 'required|in:active,inactive,banned']);
        
        try {
            $seller = Seller::findOrFail($sellerId);
            $seller->update(['status' => $request->status]); 

            return response()->json(['success' => 1, 'message' => "Seller status updated to {$request->status}."], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Seller not found.'], 404);
        }
    }
    
    
    public function listAllUsers()
    {
        $users = User::select('id', 'name', 'email', 'role', 'created_at')
                     ->whereIn('role', ['user', 'delivery']) 
                     ->orderBy('created_at', 'desc')
                     ->paginate(20);

        return response()->json(['success' => 1, 'data' => $users], 200);
    }
    
    public function updateUserRole(Request $request, $userId)
    {
        $request->validate(['role' => 'required|in:user,banned,delivery']); 
        
        try {
            $user = User::findOrFail($userId);
            if ($user->id === $request->user()->id) {
                return response()->json(['success' => 0, 'message' => 'Cannot modify your own role.'], 403);
            }
            
            $user->update(['role' => $request->role]);

            return response()->json(['success' => 1, 'message' => "User role updated to {$request->role}."], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'User not found.'], 404);
        }
    }
    
    
    public function listAllProducts(Request $request)
    {
        $query = Product::with('seller:id,name');

        if ($request->has('seller_id')) {
             $query->where('seller_id', $request->seller_id);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return response()->json(['success' => 1, 'data' => $products], 200);
    }

    public function destroyProduct($productId)
    {
        try {
            $product = Product::findOrFail($productId);
            if ($product->image_url) {
                 Storage::disk('public')->delete($product->image_url);
            }
            $product->delete();

            return response()->json(['success' => 1, 'message' => 'Product permanently deleted by Admin.'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Product not found.'], 404);
        }
    }

    public function assignDriver(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'delivery_person_id' => 'required|integer|exists:users,id', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            $order = Order::findOrFail($orderId);

            $driver = User::findOrFail($request->delivery_person_id);
            if (!$driver->isDeliveryPerson()) {
                return response()->json(['success' => 0, 'message' => 'The assigned user is not designated as a delivery person.'], 403);
            }

            $order->delivery_person_id = $request->delivery_person_id;
            $order->status = 'Assigned'; 
            $order->save();

            return response()->json([
                'success' => 1,
                'message' => "Driver {$driver->name} successfully assigned to Order #{$orderId}. Status updated to Assigned.",
                'order_id' => $order->id,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Order or Driver not found.'], 404);
        }
    }
}
