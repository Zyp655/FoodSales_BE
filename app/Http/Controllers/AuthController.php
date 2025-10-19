<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{

    public function unifiedLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Email and Password are required!', 'errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        $user = User::where('email', $credentials['email'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            $token = $user->createToken('AuthToken', ['user'])->plainTextToken; // Đổi tên token thành chuỗi đơn giản
            
            $userData = $user->makeHidden(['email_verified_at', 'password'])->toArray();
            $userData['user_type'] = 'user'; 

            return response()->json(['success' => 1, 'message' => 'Login successful', 'data' => $userData, 'token' => $token], 200);
        } 
        
        $seller = Seller::where('email', $credentials['email'])->first();

        if ($seller && Hash::check($credentials['password'], $seller->password)) {
            $token = $seller->createToken('AuthToken', ['seller'])->plainTextToken; // Đổi tên token thành chuỗi đơn giản
            
            $sellerData = $seller->makeHidden(['email_verified_at', 'password'])->toArray();
            $sellerData['user_type'] = 'seller'; 
            
            return response()->json(['success' => 1, 'message' => 'Login successful', 'data' => $sellerData, 'token' => $token], 200);
        }
        
        return response()->json(['success' => 0, 'message' => 'Invalid email or password.'], 401);
    }
    
    public function registerUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // Sử dụng tên bảng 'users' tiêu chuẩn (hoặc tên bảng chính xác của bạn)
            'email' => 'required|string|email|max:255|unique:users,email', 
            'password' => 'required|string|min:6',
            'role' => 'required|string', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }
        
        $existingSeller = Seller::where('email', $request->email)->exists();
        if ($existingSeller) {
            return response()->json(['success' => 0, 'message' => 'Email already exists as a Seller!'], 401);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password), 
                'role' => $request->role, 
            ]);
            
            $token = $user->createToken('AuthToken', ['user'])->plainTextToken;

            return response()->json(['success' => 1, 'message' => 'User registered!', 'token' => $token], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Internal Server Error during registration.', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function registerSeller(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:sellers,email',
            'password' => 'required|string|min:6',
            'address' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }
        
        $existingUser = User::where('email', $request->email)->exists();
        if ($existingUser) {
            return response()->json(['success' => 0, 'message' => 'Email already exists as a User!'], 401);
        }

        $imagePath = '';
        
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();
            $newFileName = $request->email . "_profile" . "." . $extension;
            
            $imagePath = Storage::disk('public')->putFileAs('seller_images', $file, $newFileName);
            
            $imagePath = 'seller_images/' . $newFileName; 
        }

        try {
            $seller = Seller::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password), 
                'address' => $request->address,
                'description' => $request->description,
                'image' => $imagePath,
            ]);
            
            $token = $seller->createToken('AuthToken', ['seller'])->plainTextToken;

            return response()->json(['success' => 1, 'message' => 'Seller registered!', 'token' => $token], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }
}
