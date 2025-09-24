<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIChatLog extends Model
{
    protected $table = 'ai_chat_logs';
    protected $fillable = ['user_id','question','answer'];
}
