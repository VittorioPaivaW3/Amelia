<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyDocument extends Model
{
    protected $fillable = [
        'sector',
        'title',
        'file_path',
        'text_content',
        'is_active',
        'uploaded_by',
    ];
}
