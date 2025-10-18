<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DistanceService
{
    // API Public của OSRM (như trong code PHP thuần của bạn)
    protected $baseUrl = 'http://router.project-osrm.org/route/v1/driving/'; 

    /**
     * Tính toán khoảng cách và thời gian giữa hai điểm (lat/lng).
     * @param float $userLat
     * @param float $userLng
     * @param float $sellerLat
     * @param float $sellerLng
     * @return array|null
     */
    public function calculateRoute(float $userLat, float $userLng, float $sellerLat, float $sellerLng): ?array
    {
        $coords = "{$userLng},{$userLat};{$sellerLng},{$sellerLat}"; 
        $apiUrl = $this->baseUrl . $coords . '?overview=false&steps=false&annotations=true';
        
        // Sử dụng Laravel HTTP Client (thay cho @file_get_contents)
        try {
            $response = Http::timeout(5)->get($apiUrl);
            
            if ($response->failed()) {
                // Ghi log lỗi nếu API thất bại
                \Log::error("OSRM API failed: " . $response->body());
                return null;
            }

            $data = $response->json();
            
            if (isset($data['code']) && $data['code'] === 'Ok' && !empty($data['routes'][0])) {
                $route = $data['routes'][0];
                
                // Trả về dữ liệu thô (giây và mét)
                return [
                    'distance_value' => $route['distance'], // mét
                    'duration_value' => $route['duration'], // giây
                ];
            }

        } catch (\Exception $e) {
            \Log::error("OSRM API Exception: " . $e->getMessage());
        }

        return null;
    }
}