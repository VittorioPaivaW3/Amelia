<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GutRequestAttachment extends Model
{
    protected $fillable = [
        'gut_request_id',
        'conversation_id',
        'message_id',
        'user_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $casts = [
        'gut_request_id' => 'integer',
        'user_id' => 'integer',
        'size' => 'integer',
    ];

    public function gutRequest()
    {
        return $this->belongsTo(GutRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
