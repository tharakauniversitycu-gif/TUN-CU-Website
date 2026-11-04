<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Docket extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'ec_position',
        'icon',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function ministries()
    {
        return $this->hasMany(Ministry::class)->orderBy('sort_order');
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }
}
