<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Seller;
use App\Models\Product;
use App\Models\Order;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function listAllSellers()
    {
        $sellers = Seller::select('id', 'name', 'email', 'address', 'description', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'data' => $sellers], 200);
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
        $request->validate(['role' => 'required|in:user,banned,delivery,admin']);

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

    public function updateSellerRole(Request $request, $sellerId)
    {
        $request->validate(['role' => 'required|in:seller,banned']);

        try {
            $seller = Seller::findOrFail($sellerId);
            $seller->update(['role' => $request->role]);
            return response()->json(['success' => 1, 'message' => "Seller role updated."], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Seller not found.'], 404);
        }
    }

    public function listAllOrders(Request $request)
    {
        $orders = Order::with(['user:id,name', 'seller:id,name', 'deliveryPerson:id,name'])
            ->orderByRaw("CASE WHEN status = 'ready_for_pickup' THEN 1 WHEN status = 'Assigned' THEN 2 WHEN status = 'Pending' THEN 3 ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'orders' => $orders], 200);
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

    public function getAllAccounts(Request $request)
    {
        $filterType = $request->query('type');

        $usersQuery = User::query();
        $sellersQuery = Seller::query();

        if ($filterType && $filterType !== 'all') {
            if (in_array($filterType, ['user', 'delivery', 'admin'])) {
                $usersQuery->where('role', $filterType);
                $sellersQuery->whereRaw('0 = 1');
            } elseif ($filterType === 'seller' || $filterType === 'banned') {
                $usersQuery->whereRaw('0 = 1');
                $sellersQuery->where('role', $filterType);
            }
        }

        $users = $usersQuery->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => null,
                'type' => 'user',
                'role' => $user->role,
                'status' => null,
            ];
        })->all();

        $sellers = $sellersQuery->get()->map(function ($seller) {
            return [
                'id' => $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
                'avatar' => $seller->image,
                'type' => 'seller',
                'role' => $seller->role,
                'status' => null,
            ];
        })->all();

        $allAccounts = array_merge($users, $sellers);
        
        $sortedAccounts = collect($allAccounts)->sortBy('name')->values();

        return response()->json(['success' => 1, 'data' => $sortedAccounts]);
    }

    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();
            return response()->json(['success' => 1, 'message' => 'User deleted successfully.']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'User not found.'], 404);
        }
    }

    public function deleteSeller($id)
    {
        try {
            $seller = Seller::findOrFail($id);
            $seller->delete();
            return response()->json(['success' => 1, 'message' => 'Seller deleted successfully.']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Seller not found.'], 404);
        }
    }

    public function adminListCategories()
    {
        $categories = Category::orderBy('name')->get();
        return response()->json(['success' => 1, 'data' => $categories], 200);
    }

    public function adminCreateCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:category,name',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['success' => 1, 'message' => 'Category created', 'data' => $category], 201);
    }

    public function adminUpdateCategory(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:category,name,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $category = Category::findOrFail($id);
            $category->update([
                'name' => $request->name,
                'description' => $request->description,
                'slug' => Str::slug($request->name),
            ]);

            return response()->json(['success' => 1, 'message' => 'Category updated', 'data' => $category], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Category not found.'], 404);
        }
    }

    public function adminDeleteCategory($id)
    {
        try {
            $category = Category::withCount('products')->findOrFail($id);

            if ($category->products_count > 0) {
                return response()->json(['success' => 0, 'message' => 'Cannot delete category with associated products.'], 409);
            }

            $category->delete();
            return response()->json(['success' => 1, 'message' => 'Category deleted successfully.']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => 0, 'message' => 'Category not found.'], 404);
        }
    }
    
    public function getConversations(Request $request)
    {
        $conversations = Conversation::with('lastMessage')
            ->has('lastMessage') 
            ->orderBy('updated_at', 'desc') 
            ->get();

        return response()->json(['success' => 1, 'data' => $conversations]);
    }
}

