<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ministry extends Model
{
    use HasFactory;

    protected $fillable = [
        'docket_id',
        'name',
        'code',
        'description',
        'registration_fee',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registration_fee' => 'integer',
        'sort_order' => 'integer',
    ];

    public function docket()
    {
        return $this->belongsTo(Docket::class);
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'member_ministry')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }
}
