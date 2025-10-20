<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AdminKnowledgeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Route::post('/ai/ask', [AIController::class, 'ask']); // 公开
Route::post('/ai/optimize-and-add', [AIController::class, 'optimizeAndAddToKnowledge'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/knowledge', [AdminKnowledgeController::class, 'index']);
    Route::post('/admin/knowledge', [AdminKnowledgeController::class, 'store']);
    Route::post('/admin/knowledge/update', [AdminKnowledgeController::class, 'update']);
    Route::post('/admin/knowledge/delete', [AdminKnowledgeController::class, 'delete']);
    
    // 用户相关路由
    Route::get('/user', [UserController::class, 'getUserInfo']);
    Route::post('/user/password', [UserController::class, 'updatePassword']);
});

Route::post('/login', function (Request $request) {
    $user = User::where('email', $request->email)->first();
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }
    $token = $user->createToken('api-token')->plainTextToken;
    return response()->json([
        'token' => $token,
        'user' => $user
    ]);
});

// 微信公众号相关路由
Route::prefix('wechat')->group(function () {
    // 微信服务器验证接口
    Route::get('/', [WeChatController::class, 'validateServer']);
    // 微信消息处理接口
    Route::post('/', [WeChatController::class, 'handleMessage']);
});
