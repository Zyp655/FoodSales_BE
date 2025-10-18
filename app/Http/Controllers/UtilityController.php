<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\DistanceService;

class UtilityController extends Controller
{
    protected $distanceService;

    public function __construct(DistanceService $distanceService)
    {
        $this->distanceService = $distanceService;
    }
    
    public function getDistanceMatrix(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_lat' => 'required|numeric',
            'user_lng' => 'required|numeric',
            'seller_lat' => 'required|numeric',
            'seller_lng' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error.', 'errors' => $validator->errors()], 422);
        }
        
        $result = $this->distanceService->calculateRoute(
            $request->user_lat, $request->user_lng, 
            $request->seller_lat, $request->seller_lng
        );

        if ($result) {
            $distanceKm = round($result['distance_value'] / 1000, 2);
            $durationMin = floor($result['duration_value'] / 60);

            return response()->json([
                'success' => 1,
                'distance_text' => "{$distanceKm} km",
                'duration_text' => "{$durationMin} phút",
                'distance_value' => $result['distance_value'],
                'duration_value' => $result['duration_value'],
            ]);
        }
        
        return response()->json(['success' => 0, 'message' => 'Route not found or service error.'], 500);
    }
}