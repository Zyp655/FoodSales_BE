<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

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
            $token = $user->createToken('AuthToken', [$user->role])->plainTextToken;
            $userData = $user->makeHidden(['email_verified_at', 'password'])->toArray();

            return response()->json([
                'success' => 1,
                'message' => 'Login successful',
                'user' => $userData,
                'token' => $token
            ], 200);
        }

        $seller = Seller::where('email', $credentials['email'])->first();

        if ($seller && Hash::check($credentials['password'], $seller->password)) {
            $token = $seller->createToken('AuthToken', ['seller'])->plainTextToken;
            $sellerData = $seller->makeHidden(['password'])->toArray();
            $sellerData['role'] = 'seller';

            return response()->json([
                'success' => 1,
                'message' => 'Login successful',
                'user' => $sellerData,
                'token' => $token
            ], 200);
        }

        return response()->json(['success' => 0, 'message' => 'Invalid email or password.'], 401);
    }

    public function registerUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $existingSeller = Seller::where('email', $request->email)->exists();
        if ($existingSeller) {
            return response()->json(['success' => 0, 'message' => 'Email already exists as a Seller!'], 409);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user',
            ]);

            return response()->json(['success' => 1, 'message' => 'User registered!', 'user' => $user->makeHidden(['password'])], 201);
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
            return response()->json(['success' => 0, 'message' => 'Email already exists as a User!'], 409);
        }

        $imagePath = null;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();
            $newFileName = str_replace(['@', '.'], '_', $request->email) . "_profile" . "." . $extension;
            $path = $file->storeAs('seller_images', $newFileName, 'public');
            $imagePath = $path;
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

            return response()->json(['success' => 1, 'message' => 'Seller registered!', 'seller' => $seller->makeHidden(['password'])], 201);
        } catch (\Exception $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json(['success' => 0, 'message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => 1, 'message' => 'Successfully logged out'], 200);
    }
    public function updateAddress(Request $request)
{
    $validator = Validator::make($request->all(), [
        'address' => 'required|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
    }

    $user = Auth::user(); 

    if (!$user) {
         return response()->json(['success' => 0, 'message' => 'User not authenticated.'], 401);
    }

    try {
        if ($user instanceof User) {
             $user->address = $request->address;
             $user->save();
        } elseif ($user instanceof Seller) {
             $user->address = $request->address;
             $user->save();
        } else {
             return response()->json(['success' => 0, 'message' => 'Invalid user type.'], 400);
        }


        $userData = $user->toArray();
        if ($user instanceof Seller) {
             $userData['role'] = 'seller'; 
        }


        return response()->json(['success' => 1, 'message' => 'Address updated successfully.', 'user' => $userData], 200);

    } catch (\Exception $e) {
        return response()->json(['success' => 0, 'message' => 'Failed to update address.', 'error' => $e->getMessage()], 500);
    }
}
}