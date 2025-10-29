<?php

namespace App\Http\Controllers;

use App\Models\DeliveryTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class DeliveryTicketController extends Controller
{
    public function createTicket(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'user') {
             return response()->json(['success' => 0, 'message' => 'Only users can apply.'], 403);
        }

        $existingTicket = DeliveryTicket::where('user_id', $user->id)->where('status', 'pending')->first();
        if ($existingTicket) {
             return response()->json(['success' => 0, 'message' => 'You already have a pending application.'], 409);
        }
        if ($user->isDeliveryPerson()) {
             return response()->json(['success' => 0, 'message' => 'You are already a delivery driver.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => 'required|numeric|digits:10',
            'id_card_number' => 'required|string|numeric|digits_between:9,12', 
            'id_card_image' => 'required|image|mimes:jpeg,png,jpg|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('id_card_image')) {
            $imagePath = $request->file('id_card_image')->store('id_cards', 'public');
        }

        try {
            $ticket = DeliveryTicket::create([
                'user_id' => $user->id,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'id_card_number' => $request->id_card_number,
                'id_card_image_path' => $imagePath,
                'status' => 'pending',
            ]);

            return response()->json(['success' => 1, 'message' => 'Application submitted successfully!', 'ticket' => $ticket], 201);

        } catch (\Exception $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json(['success' => 0, 'message' => 'Failed to submit application.', 'error' => $e->getMessage()], 500);
        }
    }
}