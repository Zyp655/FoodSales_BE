<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Seller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
                     ->where('role', 'user') 
                     ->orderBy('created_at', 'desc')
                     ->paginate(20);

        return response()->json(['success' => 1, 'data' => $users], 200);
    }
    
    public function updateUserRole(Request $request, $userId)
    {
        $request->validate(['role' => 'required|in:user,banned']);
        
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
            // Xóa file ảnh trước khi xóa bản ghi (Tốt nhất)
            if ($product->image_url) {
                 Storage::disk('public')->delete($product->image_url);
            }
            $product->delete();

            return response()->json(['success' => 1, 'message' => 'Product permanently deleted by Admin.'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Product not found.'], 404);
        }
    }
}