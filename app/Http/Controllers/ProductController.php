<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
}