<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * 修改用户密码
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updatePassword(Request $request)
    {
        // 验证请求数据
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // 获取当前登录用户
        $user = $request->user();

        // 验证当前密码
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码不正确'],
            ]);
        }

        // 更新密码
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => '密码修改成功，请重新登录',
        ]);
    }

    /**
     * 获取当前用户信息
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getUserInfo(Request $request)
    {
        return response()->json($request->user());
    }
}
