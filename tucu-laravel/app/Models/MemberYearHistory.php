<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberYearHistory extends Model
{
    use HasFactory;

    protected $table = 'member_year_history';

    protected $fillable = [
        'member_id',
        'academic_year_id',
        'year_of_study',
        'action',
        'notes',
        'confirmed_at',
    ];

    protected $casts = [
        'year_of_study' => 'integer',
        'confirmed_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
