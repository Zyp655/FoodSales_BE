<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SellerProductController extends Controller
{
    public function getProductsBySeller(Request $request)
    {
        // Logic cũ từ ProductController::getProductsBySeller
        $sellerId = $request->user()->id; 
        
        $productsList = Product::where('seller_id', $sellerId)
                             ->orderBy('created_at', 'desc')
                             ->get();

        if ($productsList->isEmpty()) {
            return response()->json(['success' => 0, 'message' => 'No products found for this seller.'], 404);
        }
        
        return response()->json(['success' => 1, 'data' => $productsList]);
    }

    public function addProduct(Request $request) // Đổi tên hàm store -> addProduct cho khớp routes
    {
        // Logic cũ từ ProductController::store
        $sellerId = $request->user()->id; 
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'price_per_kg' => 'required|numeric|min:0.01',
            'description' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            // LƯU Ý: image_url trong Model Product có thể khác 'image' trong Migration
            $imagePath = $request->file('image')->store('product_images', 'public');
        }

        $product = Product::create([
            'seller_id' => $sellerId,
            'name' => $request->name,
            'category_id' => $request->category_id,
            'price_per_kg' => $request->price_per_kg,
            'description' => $request->description,
            'image_url' => $imagePath,
        ]);

        return response()->json([
            'success' => 1, 
            'message' => 'Product successfully added!', 
            'product' => $product
        ], 201);
    }
    
    public function updateProduct(Request $request, $productId) // Đổi tên hàm update -> updateProduct
    {
        // Logic cũ từ ProductController::update
        $sellerId = $request->user()->id; 
        
        // ... (Logic Validation) ...
        
        $product = Product::where('id', $productId)->where('seller_id', $sellerId)->first();
        // ... (Logic xử lý) ...
        
        return response()->json(['success' => 1, 'message' => 'Product successfully updated!'], 200);
    }

    public function deleteProduct(Request $request, $productId) // Đổi tên hàm destroy -> deleteProduct
    {
        // Logic cũ từ ProductController::destroy
        $sellerId = $request->user()->id; 
        
        $product = Product::where('id', $productId)->where('seller_id', $sellerId)->first();
        // ... (Logic xử lý) ...

        return response()->json(['success' => 1, 'message' => 'Product successfully deleted!'], 200);
    }
}