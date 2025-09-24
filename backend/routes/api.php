<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AdminKnowledgeController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

Route::post('/ai/ask', [AIController::class, 'ask']); // 公开

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/knowledge', [AdminKnowledgeController::class, 'index']);
    Route::post('/admin/knowledge', [AdminKnowledgeController::class, 'store']);
    
    // 用户相关路由
    Route::get('/user', [UserController::class, 'getUserInfo']);
    Route::post('/user/password', [UserController::class, 'updatePassword']);
});

Route::post('/login', function (Request $request) {
    $user = User::where('email', $request->email)->first();
    if (!$user || !\Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }
    $token = $user->createToken('api-token')->plainTextToken;
    return response()->json([
        'token' => $token,
        'user' => $user
    ]);
});
