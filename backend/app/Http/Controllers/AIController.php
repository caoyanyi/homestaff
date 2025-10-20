<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AIChatLog;
use App\Models\Knowledge;
use Illuminate\Support\Facades\Http;

class AIController extends Controller
{
    public function ask(Request $request)
    {
        $question = $request->input('question');

        $search = Http::post(env('EMBEDDING_API_URL') . '/search', [
            'text' => $question,
            'top_k' => 5
        ]);

        $related = $search->json()['results'] ?? [];
        $context = '';
        foreach ($related as $doc) {
            $context .= $doc['text'] . "\n";
        }

        $openaiResponse = Http::withToken(env('AI_API_KEY'))
            ->post(env('AI_API_URL') . '/chat/completions', [
                'model' => env('AI_MODEL'),
                'messages' => [
                    ['role' => 'system', 'content' => env('SYSTEM_PROMPT') . '根据提供的知识库回答用户问题。'],
                    ['role' => 'user', 'content' => "已知资料:\n$context\n\n问题:$question"]
                ]
            ])->json();
        $answer = $openaiResponse['choices'][0]['message']['content'] ?? '无法生成回答';

        // 清理回答内容，移除可能导致微信消息格式问题的字符
        $answer = $this->cleanupAnswer($answer);

        // 存储聊天日志 - 允许外部传入用户ID（如微信用户）
        $userId = $request->input('user_id', ($request->user() ? $request->user()->id : 0));
        AIChatLog::create([
            'user_id' => $userId,
            'question' => $question,
            'answer' => $answer
        ]);

        return ['question' => $question, 'answer' => $answer, 'context_used' => $related];
    }
    
    /**
     * 清理回答内容，确保适合微信消息格式
     */
    protected function cleanupAnswer($answer)
    {
        // 移除过多的空行
        $answer = preg_replace('/\n{3,}/', "\n\n", $answer);
        
        // 移除Markdown格式（如果有），微信消息不支持Markdown
        $answer = preg_replace('/[#*`]/', '', $answer);
        
        // 替换特殊字符，确保微信消息格式正确
        $answer = str_replace(['\r\n', '\r'], "\n", $answer);
        
        return trim($answer);
    }

    /**
     * 优化用户输入并添加到知识库
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function optimizeAndAddToKnowledge(Request $request)
    {
        $question = $request->input('question');
        $answer = $request->input('answer');

        // 使用AI优化用户输入和答案，生成适合知识库的内容
        $optimizedContentResponse = Http::withToken(env('AI_API_KEY'))
            ->post(env('AI_API_URL') . '/chat/completions', [
                'model' => env('AI_MODEL'),
                'messages' => [
                    ['role' => 'system', 'content' => '你是一个知识整理专家，需要将用户的问题和AI的回答整理成适合知识库的条目。保留核心信息，使其结构化、易于理解，并添加适当的分类和标签。'],
                    ['role' => 'user', 'content' => "用户问题: $question\n\nAI回答: $answer\n\n请将上述内容整理成适合知识库的条目，返回格式为JSON，包含title(标题)、content(内容)、category(分类)和tags(标签数组)字段。不要添加任何额外的解释文字。"]
                ]
            ])->json();

        $optimizedContent = $optimizedContentResponse['choices'][0]['message']['content'] ?? '';
        
        // 尝试解析JSON响应
        try {
            // 移除可能的Markdown代码块标记
            $cleanContent = preg_replace('/^```json\r?\n|\r?\n```$/s', '', $optimizedContent);
            $knowledgeData = json_decode($cleanContent, true);
            
            if (!isset($knowledgeData['title'], $knowledgeData['content'])) {
                return response()->json(['status' => 'error', 'message' => '无法解析优化后的内容：缺少必要字段', 'raw_content' => $optimizedContent], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => '解析优化内容失败: ' . $e->getMessage(), 'raw_content' => $optimizedContent], 400);
        }
        
        // 创建知识库条目
        $knowledge = Knowledge::create([
            'title' => $knowledgeData['title'],
            'content' => $knowledgeData['content'],
            'tags' => $knowledgeData['tags'] ?? [],
            'category' => $knowledgeData['category'] ?? env('SYSTEM_MODE')
        ]);

        // 添加到向量存储 - 修复URL格式错误
        $vectorResponse = Http::post(env('EMBEDDING_API_URL') . '/add-doc', [
            'doc_id' => $knowledge->id,
            'text' => $knowledgeData['content']
        ]);

        if ($vectorResponse->failed()) {
            // 记录错误但继续返回成功，因为知识库已创建
            
            // 尝试直接通过HTTP请求添加到向量服务器（备用方法）
            try {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => env('EMBEDDING_API_URL') . '/add-doc',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'doc_id' => $knowledge->id,
                        'text' => $knowledgeData['content']
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json'
                    ]
                ]);
                curl_exec($curl);
                curl_close($curl);
            } catch (\Exception $e) {
                // 忽略备用方法的错误
            }
        }

        return response()->json(['status' => 'ok', 'id' => $knowledge->id, 'optimized' => $knowledgeData]);
    }
}
