<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpiritualYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'theme',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }
}
