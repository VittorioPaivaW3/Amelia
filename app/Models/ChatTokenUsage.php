<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatTokenUsage extends Model
{
    protected $fillable = [
        'user_id',
        'gut_request_id',
        'mode',
        'sector',
        'model',
        'conversation_id',
        'message_id',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'input_cost',
        'output_cost',
        'total_cost',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'gut_request_id' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'input_cost' => 'decimal:6',
        'output_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gutRequest()
    {
        return $this->belongsTo(GutRequest::class);
    }
}
