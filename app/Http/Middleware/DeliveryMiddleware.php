<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeliveryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->isDeliveryPerson()) {
            return $next($request);
        }

        return response()->json([
            'success' => 0,
            'message' => 'Access Denied. Delivery role required.'
        ], 403);
    }
}