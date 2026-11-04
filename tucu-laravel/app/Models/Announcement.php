<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'target_type',
        'docket_id',
        'ministry_id',
        'author_member_id',
        'author_admin_id',
        'author_name',
        'author_role_label',
        'is_pinned',
        'published_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function docket()
    {
        return $this->belongsTo(Docket::class);
    }

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }

    public function authorMember()
    {
        return $this->belongsTo(Member::class, 'author_member_id');
    }

    public function authorAdmin()
    {
        return $this->belongsTo(Admin::class, 'author_admin_id');
    }
}
