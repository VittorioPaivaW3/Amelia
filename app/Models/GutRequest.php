<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GutRequest extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'sector',
        'gravity',
        'urgency',
        'trend',
        'score',
        'status',
        'response_text',
    ];

    protected $casts = [
        'gravity' => 'integer',
        'urgency' => 'integer',
        'trend' => 'integer',
        'score' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
