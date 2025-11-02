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
use Illuminate\Validation\Rules\Password;
use App\Jobs\SendWelcomeEmail;
use Illuminate\Support\Facades\Cache;

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
            'password' => ['required', 'string', Password::min(6)],
            'address' => 'nullable|string|max:255',
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
                'address' => $request->address,
            ]);

            SendWelcomeEmail::dispatch($user->email, $user->name);

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
            'password' => ['required', 'string', Password::min(6)],
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
            
            SendWelcomeEmail::dispatch($seller->email, $seller->name);

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
            $user->address = $request->address;
            $user->save();

            $userData = $user->toArray();
            if ($user instanceof Seller) {
                $userData['role'] = 'seller';
            }

            return response()->json(['success' => 1, 'message' => 'Address updated successfully.', 'user' => $userData], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Failed to update address.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|numeric|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => 0, 'message' => 'User not authenticated.'], 401);
        }

        try {
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
            
            $user->save();

            $userData = $user->toArray();
            if ($user instanceof Seller) {
                $userData['role'] = 'seller';
            }

            return response()->json(['success' => 1, 'message' => 'Profile updated successfully.', 'user' => $userData], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Failed to update profile.', 'error' => $e->getMessage()], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(6), 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => 0, 'message' => 'User not authenticated.'], 401);
        }

        if (!Hash::check($request->current_password, $user->password)) {
             return response()->json(['success' => 0, 'message' => 'Current password does not match.'], 401);
        }

        try {
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json(['success' => 1, 'message' => 'Password changed successfully.'], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'message' => 'Failed to change password.', 'error' => $e->getMessage()], 500);
        }
    }
    public function updateSellerInfo(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user instanceof Seller) {
             return response()->json(['success' => 0, 'message' => 'Not authenticated as a seller.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:sellers,email,' . $user->id,
            'phone' => 'nullable|numeric|digits:10',
            'address' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            $dataToUpdate = $request->only(['name', 'email', 'phone', 'address', 'description']);

            if ($request->hasFile('image')) {
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();
                $newFileName = str_replace(['@', '.'], '_', $request->email) . "_profile" . "." . $extension;
                $path = $file->storeAs('seller_images', $newFileName, 'public');
                $dataToUpdate['image'] = $path; 
            }

            $user->update($dataToUpdate);

            $user->refresh();
            
            Cache::forget('seller_analytics_' . $user->id);

            $userData = $user->toArray();
            $userData['role'] = 'seller'; 

            return response()->json(['success' => 1, 'message' => 'Seller info updated successfully.', 'user' => $userData], 200);

        } catch (\Exception $e) {
             return response()->json(['success' => 0, 'message' => 'Failed to update seller info.', 'error' => $e->getMessage()], 500);
        }
    }
}