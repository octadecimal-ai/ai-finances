<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'message' => 'Analytics endpoint',
            'user_id' => $user->id,
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'message' => 'Statistics endpoint',
            'user_id' => $user->id,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'message' => 'Trends endpoint',
            'user_id' => $user->id,
        ]);
    }
} 