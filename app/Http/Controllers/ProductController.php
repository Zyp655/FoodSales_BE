<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    
    public function getSingleProduct(Request $request, $productId)
    {
        try {
            $product = Product::with('seller:id,name,phone') 
                              ->findOrFail($productId);
            
            return response()->json([
                'success' => 1, 
                'data' => $product
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => 0, 
                'message' => 'Product not found.'
            ], 404);
        }
    }

    public function getAllProductsOrSearch(Request $request)
    {
        $query = Product::with('seller:id,name');

        if ($request->has('search') && $request->search != '') {
            $keyword = $request->search;
            $query->where('name', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
        }
        
        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);

        if ($products->isEmpty()) {
            return response()->json(['success' => 1, 'message' => 'No products found.', 'data' => []], 200);
        }

        return response()->json(['success' => 1, 'data' => $products], 200);
    }
    
    public function getProductsBySeller(Request $request)
    {
        $sellerId = $request->user()->id; 
        
        $productsList = Product::where('seller_id', $sellerId)
                        ->orderBy('created_at', 'desc')
                        ->get();

        if ($productsList->isEmpty()) {
            return response()->json(['success' => 0, 'message' => 'No products found for this seller.'], 404);
        }
        
        return response()->json(['success' => 1, 'data' => $productsList]);
    }

    public function store(Request $request)
    {
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
    
    public function update(Request $request, $productId)
    {
        $sellerId = $request->user()->id; 
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|integer|exists:categories,id',
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
            if ($product->image_url) {
                 Storage::disk('public')->delete($product->image_url);
            }
            $imagePath = $request->file('image')->store('product_images', 'public');
            $dataToUpdate['image_url'] = $imagePath;
        }

        $product->update($dataToUpdate);

        return response()->json(['success' => 1, 'message' => 'Product successfully updated!'], 200);
    }

    public function destroy(Request $request, $productId)
    {
        $sellerId = $request->user()->id; 
        
        $product = Product::where('id', $productId)->where('seller_id', $sellerId)->first();

        if (!$product) {
            return response()->json(['success' => 0, 'message' => 'Product not found or access denied.'], 403);
        }
        
        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json(['success' => 1, 'message' => 'Product successfully deleted!'], 200);
    }
}