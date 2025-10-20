<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WeChatUser;
use App\Models\AIChatLog;
use App\Http\Controllers\AIController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WeChatController extends Controller
{
    // 微信公众号配置
    protected $appId;
    protected $appSecret;
    protected $token;
    
    public function __construct()
    {
        // 从环境变量获取配置（明文模式只需要appId和token）
        $this->appId = env('WECHAT_APPID');
        $this->token = env('WECHAT_TOKEN', 'default_token');
        
        // 验证必要的配置
        if (!$this->appId || !$this->token) {
            throw new \Exception('WeChat configuration missing: WECHAT_APPID and WECHAT_TOKEN are required');
        }
        
        Log::info('WeChat Controller initialized in plain text mode', [
            'app_id_last_8' => substr($this->appId, -8)
        ]);
    }
    
    /**
     * 验证服务器
     * 使用明文模式验证
     */
    public function validateServer(Request $request)
    {
        $signature = $request->input('signature');
        $timestamp = $request->input('timestamp');
        $nonce = $request->input('nonce');
        $token = $this->token;
        $echostr = $request->input('echostr');
        
        // 明文模式验证
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $hashCode = sha1($tmpStr);
        
        Log::info('WeChat server validation in plain text mode', [
            'signature_match' => $hashCode === $signature
        ]);
        
        if ($hashCode === $signature) {
            return response($echostr, 200)->header('Content-Type', 'text/plain');
        } else {
            Log::error('WeChat server validation failed: invalid signature');
            return response('Invalid signature', 403);
        }
    }
    
    /**
     * 处理微信消息
     * 使用明文模式处理，不进行加密解密
     */
    public function handleMessage(Request $request)
    {
        $xml = $request->getContent();
        $data = $this->parseXml($xml);
        
        // 明文模式，验证必要字段
        if (!is_array($data) || !isset($data['MsgType'], $data['FromUserName'], $data['ToUserName'])) {
            // 记录错误日志
            Log::error('Invalid WeChat message format: ' . $xml);
            
            // 返回空响应
            return '';
        }
        
        Log::info('Processing WeChat message in plain text mode', [
            'msg_type' => isset($data['MsgType']) ? $data['MsgType'] : 'unknown',
            'from_user' => isset($data['FromUserName']) ? $data['FromUserName'] : 'unknown'
        ]);
        
        // 获取消息类型和内容
        $msgType = $data['MsgType'];
        $fromUserName = $data['FromUserName'];
        $toUserName = $data['ToUserName'];
        
        // 根据消息类型处理
        switch ($msgType) {
            case 'text':
                return $this->handleTextMessage($data, $fromUserName, $toUserName);
            case 'event':
                return $this->handleEventMessage($data, $fromUserName, $toUserName);
            default:
                // 记录未处理的消息类型
                Log::info('Unsupported WeChat message type: ' . $msgType);
                return $this->responseText($toUserName, $fromUserName, '暂不支持该类型消息');
        }
    }
    

    
    /**
     * 处理文本消息
     */
    protected function handleTextMessage($data, $fromUserName, $toUserName)
    {
        $content = $data['Content'];
        
        // 为每个微信用户创建会话ID
        $sessionKey = 'wechat_session_' . $fromUserName;
        $sessionId = Cache::get($sessionKey, function() use ($sessionKey) {
            $newId = Str::random(32);
            Cache::put($sessionKey, $newId, 7 * 24 * 60); // 7天有效期
            return $newId;
        });
        
        // 获取或创建微信用户
        $user = WeChatUser::firstOrCreate(
            ['openid' => $fromUserName],
            ['session_id' => $sessionId, 'last_active' => now()]
        );
        
        // 更新用户最后活跃时间
        $user->update(['last_active' => now()]);
        
        // 调用AI服务获取回答
        $response = $this->callAIService($content, $user->id);
        
        // 保存聊天记录
        AIChatLog::create([
            'user_id' => $user->id,
            'question' => $content,
            'answer' => $response['answer'],
            'is_wechat' => true
        ]);
        
        // 返回AI回答
        return $this->responseText($toUserName, $fromUserName, $response['answer']);
    }
    
    /**
     * 处理事件消息
     */
    protected function handleEventMessage($data, $fromUserName, $toUserName)
    {
        $event = $data['Event'];
        
        switch ($event) {
            case 'subscribe':
                // 处理关注事件
                $this->createWeChatUser($fromUserName);
                return $this->responseText($toUserName, $fromUserName, '欢迎关注家政AI知识库！\n\n请输入您的问题，我将为您提供专业的家政咨询服务。');
            
            case 'unsubscribe':
                // 处理取消关注事件
                $this->updateWeChatUserStatus($fromUserName, 0);
                return '';
                
            default:
                return $this->responseText($toUserName, $fromUserName, '收到事件：' . $event);
        }
    }
    
    /**
     * 调用AI服务
     */
    protected function callAIService($question, $userId)
    {
        // 直接复用AIController中的逻辑，传入用户ID
        $aiController = app(AIController::class);
        $request = new Request(['question' => $question, 'user_id' => $userId]);
        $response = $aiController->ask($request);
        
        return $response;
    }
    
    /**
     * 创建微信用户
     */
    protected function createWeChatUser($openid)
    {
        WeChatUser::firstOrCreate(['openid' => $openid]);
    }
    
    /**
     * 更新微信用户状态
     */
    protected function updateWeChatUserStatus($openid, $status)
    {
        WeChatUser::where('openid', $openid)->update(['status' => $status]);
    }
    
    /**
     * 解析XML数据
     */
    protected function parseXml($xml)
    {
        libxml_disable_entity_loader(true);
        $data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($data), true);
    }
    
    /**
     * 回复文本消息
     */
    protected function responseText($toUserName, $fromUserName, $content)
    {
        // 处理微信消息长度限制（最多2048字节）
        $content = mb_substr($content, 0, 500, 'UTF-8');
        
        $xml = "<xml>
                <ToUserName><![CDATA[{$fromUserName}]]></ToUserName>
                <FromUserName><![CDATA[{$toUserName}]]></FromUserName>
                <CreateTime>" . time() . "</CreateTime>
                <MsgType><![CDATA[text]]></MsgType>
                <Content><![CDATA[{$content}]]></Content>
                </xml>";
                
        return $xml;
    }
    
    /**
     * 获取微信access_token
     */
    public function getAccessToken()
    {
        // 验证appSecret是否已配置
        $this->appSecret = env('WECHAT_APPSECRET');
        if (!$this->appSecret) {
            Log::error('WeChat appSecret is not configured');
            return null;
        }
        
        $cacheKey = 'wechat_access_token';
        
        // 尝试从缓存获取
        $accessToken = Cache::get($cacheKey);
        if ($accessToken) {
            return $accessToken;
        }
        
        // 从微信服务器获取
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appId}&secret={$this->appSecret}";
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        
        if (isset($result['access_token']) && isset($result['expires_in'])) {
            // 缓存access_token，有效期减10分钟避免过期
            Cache::put($cacheKey, $result['access_token'], ($result['expires_in'] - 600) / 60);
            return $result['access_token'];
        }
        
        return null;
    }
}