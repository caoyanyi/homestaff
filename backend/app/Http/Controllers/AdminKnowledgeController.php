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

        Http::post('http://127.0.0.1:9000/add-doc', [
            'doc_id' => $knowledge->id,
            'text'   => $knowledge->content
        ]);

        return response()->json(['status' => 'ok', 'id' => $knowledge->id]);
    }
}
