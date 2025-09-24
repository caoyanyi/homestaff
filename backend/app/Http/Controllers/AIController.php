<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AIChatLog;
use Illuminate\Support\Facades\Http;

class AIController extends Controller
{
    public function ask(Request $request)
    {
        $question = $request->input('question');

        $search = Http::post(env('EMBEDDING_API_URL'), [
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
                    ['role' => 'system', 'content' => '你是一个家政顾问AI，根据提供的知识库回答用户问题。'],
                    ['role' => 'user', 'content' => "已知资料:\n$context\n\n问题:$question"]
                ]
            ])->json();
        $answer = $openaiResponse['choices'][0]['message']['content'] ?? '无法生成回答';

        AIChatLog::create([
            'user_id' => $request->user() ? $request->user()->id : 0,
            'question' => $question,
            'answer' => $answer
        ]);

        return ['question' => $question, 'answer' => $answer, 'context_used' => $related];
    }
}
