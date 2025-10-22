<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json(['success' => 0, 'message' => 'Authentication required.'], 401);
        }

        if ($request->user()->role !== 'admin') {
             return response()->json(['success' => 0, 'message' => 'Access Forbidden. Admin only.'], 403);
        }

        return $next($request);
    }
}
