<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'read_status',
        'sent_at',
    ];

    protected $casts = [
        'read_status' => 'boolean',
        'sent_at' => 'datetime',
    ];
}
