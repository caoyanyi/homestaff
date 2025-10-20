<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeChatUser extends Model
{
    use HasFactory;
    
    /**
     * 表名
     */
    protected $table = 'wechat_users';
    
    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'openid',
        'unionid',
        'nickname',
        'avatar',
        'gender',
        'country',
        'province',
        'city',
        'language',
        'session_id',
        'last_active',
        'status'
    ];
    
    /**
     * 时间戳字段
     */
    protected $dates = ['last_active'];
    
    /**
     * 获取用户的聊天记录
     */
    public function chatLogs()
    {
        return $this->hasMany(AIChatLog::class, 'user_id');
    }
    
    /**
     * 检查用户是否有效
     */
    public function isActive()
    {
        return $this->status == 1;
    }
    
    /**
     * 获取用户的微信信息
     */
    public function getUserInfo($accessToken)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$accessToken}&openid={$this->openid}&lang=zh_CN";
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $result = curl_exec($ch);
            curl_close($ch);
            
            return json_decode($result, true);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * 更新用户信息
     */
    public function updateUserInfo($userInfo)
    {
        $this->update([
            'nickname' => $userInfo['nickname'] ?? $this->nickname,
            'avatar' => $userInfo['headimgurl'] ?? $this->avatar,
            'gender' => $userInfo['sex'] ?? $this->gender,
            'country' => $userInfo['country'] ?? $this->country,
            'province' => $userInfo['province'] ?? $this->province,
            'city' => $userInfo['city'] ?? $this->city,
            'language' => $userInfo['language'] ?? $this->language,
            'unionid' => $userInfo['unionid'] ?? $this->unionid,
        ]);
        
        return $this;
    }
}