<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GutRequest extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'summary',
        'message',
        'sector',
        'gravity',
        'urgency',
        'trend',
        'score',
        'status',
        'accepted_by',
        'accepted_at',
        'rejection_reason',
        'response_text',
    ];

    protected $casts = [
        'gravity' => 'integer',
        'urgency' => 'integer',
        'trend' => 'integer',
        'score' => 'integer',
        'accepted_by' => 'integer',
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function attachments()
    {
        return $this->hasMany(GutRequestAttachment::class);
    }
}
