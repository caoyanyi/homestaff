<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行数据库迁移
     */
    public function up(): void
    {
        Schema::create('wechat_users', function (Blueprint $table) {
            $table->id();
            $table->string('openid')->unique()->index(); // 微信用户唯一标识
            $table->string('unionid')->nullable()->index(); // 微信开放平台唯一标识
            $table->string('nickname')->nullable(); // 昵称
            $table->string('avatar')->nullable(); // 头像URL
            $table->tinyInteger('gender')->nullable(); // 性别 0未知 1男 2女
            $table->string('country')->nullable(); // 国家
            $table->string('province')->nullable(); // 省份
            $table->string('city')->nullable(); // 城市
            $table->string('language')->nullable(); // 语言
            $table->string('session_id')->nullable()->index(); // 会话ID
            $table->timestamp('last_active')->nullable(); // 最后活跃时间
            $table->tinyInteger('status')->default(1); // 状态 0未关注 1已关注
            $table->timestamps();
        });
        
        // 修改AI聊天记录表，添加微信标识字段
        Schema::table('ai_chat_logs', function (Blueprint $table) {
            $table->boolean('is_wechat')->default(false)->after('answer'); // 是否微信聊天
        });
    }
    
    /**
     * 回滚数据库迁移
     */
    public function down(): void
    {
        // 移除AI聊天记录表的字段
        Schema::table('ai_chat_logs', function (Blueprint $table) {
            $table->dropColumn('is_wechat');
        });
        
        // 删除微信用户表
        Schema::dropIfExists('wechat_users');
    }
};