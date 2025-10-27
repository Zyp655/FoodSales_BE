<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Category;
use App\Models\Seller; 

class ProductController extends Controller
{
    public function getSingleProduct(Request $request, $productId)
    {
        try {
            $product = Product::with('seller:id,name,image,address,description')
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

   
    public function searchProducts(Request $request)
    {
        $query = Product::with('seller:id,name,image,address'); 

        if ($request->has('seller_id') && $request->seller_id != '') {
             $query->where('seller_id', $request->seller_id);
        }

        if ($request->has('search') && $request->search != '') {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                 $q->where('name', 'like', "%{$keyword}%")
                   ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        if ($products->isEmpty()) {
            return response()->json(['success' => 1, 'message' => 'No products found.', 'products' => []], 200);
        }

        return response()->json(['success' => 1, 'products' => $products], 200);
    }


    public function listCategories(Request $request)
    {
        $categories = Category::all();
        return response()->json(['success' => 1, 'categories' => $categories]);
    }

    public function combinedSearch(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
          
            return response()->json(['success' => 1, 'results' => ['products' => [], 'sellers' => []]]);
        }

       
        $products = Product::with('seller:id,name,image,address')
                           ->where('name', 'LIKE', "%{$query}%")
                           ->limit(10) 
                           ->get();

        $sellers = Seller::select('id', 'name', 'image', 'address', 'description') 
                         ->where('name', 'LIKE', "%{$query}%")
                         ->limit(5) 
                         ->get();

        return response()->json([
            'success' => 1,
            'results' => [
                'products' => $products,
                'sellers' => $sellers,
            ]
        ]);
    }
   

}