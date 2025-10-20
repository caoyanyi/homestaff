<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Knowledge;
use Illuminate\Support\Facades\Http;

class AdminKnowledgeController extends Controller
{
    public function index()
    {
        return Knowledge::all();
    }

    public function store(Request $request)
    {
        $knowledge = Knowledge::create($request->only(['title','content','tags','category']));

        Http::post(env('EMBEDDING_API_URL') . '/add-doc', [
            'doc_id' => $knowledge->id,
            'text'   => $knowledge->content
        ]);

        return response()->json(['status' => 'ok', 'id' => $knowledge->id]);
    }
    
    public function update(Request $request)
    {
        $knowledge = Knowledge::findOrFail($request->id);
        $knowledge->update($request->only(['title','content','tags','category']));
        
        // 更新向量数据库
        Http::post(env('EMBEDDING_API_URL') . '/update-doc', [
            'doc_id' => $knowledge->id,
            'text'   => $knowledge->content
        ]);
        
        return response()->json(['status' => 'ok']);
    }
    
    public function delete(Request $request)
    {
        $knowledge = Knowledge::findOrFail($request->id);
        $knowledge->delete();
        
        // 从向量数据库中删除
        Http::post(env('EMBEDDING_API_URL') . '/delete-doc', [
            'doc_id' => $request->id
        ]);
        
        return response()->json(['status' => 'ok']);
    }
}
