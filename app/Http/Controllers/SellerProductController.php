<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Seller;
class SellerProductController extends Controller
{  
    public function listSellers(Request $request)
    {
        $sellers = Seller::select('id', 'name', 'image', 'address', 'description')
                         ->get();

        if ($sellers->isEmpty()) {
            return response()->json(['success' => 1, 'message' => 'No sellers found.', 'sellers' => []], 200);
        }

        return response()->json(['success' => 1, 'sellers' => $sellers]); 
    } 
    public function getProductsBySeller(Request $request)
    {
        $sellerId = $request->user()->id; 
        
        $productsList = Product::where('seller_id', $sellerId)
                             ->get();

        if ($productsList->isEmpty()) {
            return response()->json(['success' => 1, 'message' => 'No products found for this seller.', 'data' => []], 200);
        }
        
        return response()->json(['success' => 1, 'data' => $productsList]);
    }

    public function addProduct(Request $request)
    {
        $sellerId = $request->user()->id; 
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:category,id',
            'price_per_kg' => 'required|numeric|min:0.01',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product_images', 'public');
        }

        $product = Product::create([
            'seller_id' => $sellerId,
            'name' => $request->name,
            'category_id' => $request->category_id,
            'price_per_kg' => $request->price_per_kg,
            'description' => $request->description,
            'image' => $imagePath, 
        ]);

        return response()->json([
            'success' => 1, 
            'message' => 'Product successfully added!', 
            'product' => $product
        ], 201);
    }
    
    public function updateProduct(Request $request, $productId) 
    {
        $sellerId = $request->user()->id; 
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|integer|exists:category,id',
            'price_per_kg' => 'sometimes|required|numeric|min:0.01',
            'description' => 'sometimes|required|string',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $product = Product::where('id', $productId)->where('seller_id', $sellerId)->first();

        if (!$product) {
            return response()->json(['success' => 0, 'message' => 'Product not found or access denied.'], 403);
        }

        $dataToUpdate = $request->only(['name', 'category_id', 'price_per_kg', 'description']);

        if ($request->hasFile('image')) {
           
            if ($product->image) { 
                Storage::disk('public')->delete($product->image);
            }
            $imagePath = $request->file('image')->store('product_images', 'public');
            $dataToUpdate['image'] = $imagePath; 
        }

        $product->update($dataToUpdate);

        return response()->json(['success' => 1, 'message' => 'Product successfully updated!', 'product' => $product], 200);
    }

    public function deleteProduct(Request $request, $productId) 
    {
        $sellerId = $request->user()->id; 
        
        $product = Product::where('id', $productId)->where('seller_id', $sellerId)->first();

        if (!$product) {
            return response()->json(['success' => 0, 'message' => 'Product not found or access denied.'], 403);
        }
        
        
        if ($product->image) { 
            Storage::disk('public')->delete($product->image); 
        }

        $product->delete();

        return response()->json(['success' => 1, 'message' => 'Product successfully deleted!'], 200);
    }
}