<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'full_name',
        'reg_number',
        'email',
        'password',
        'phone',
        'course',
        'course_duration',
        'year_of_study',
        'membership_category',
        'role',
        'ec_position',
        'status',
        'is_year_confirmed',
        'year_confirmed_at',
        'became_associate_at',
        'profile_photo_url',
        'admin_notes',
        'joined_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'year_of_study'     => 'integer',
        'course_duration'   => 'integer',
        'is_year_confirmed' => 'boolean',
        'year_confirmed_at' => 'datetime',
        'became_associate_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'joined_at'         => 'datetime',
        'password'          => 'hashed',
    ];

    /**
     * Ministries that this member belongs to (max 3).
     */
    public function ministries()
    {
        return $this->belongsToMany(Ministry::class, 'member_ministry')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Distinct dockets derived from the member's ministries.
     */
    public function dockets()
    {
        return Docket::whereIn('id', $this->ministries()->pluck('docket_id'))->get();
    }

    /**
     * Academic year progression history.
     */
    public function yearHistory()
    {
        return $this->hasMany(MemberYearHistory::class)->orderByDesc('confirmed_at');
    }

    /**
     * A member has many prayer requests.
     */
    public function prayerRequests()
    {
        return $this->hasMany(PrayerRequest::class);
    }

    /**
     * A member has many event registrations.
     */
    public function eventRegistrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * A member may have a newsletter subscription record.
     */
    public function newsletterSubscription()
    {
        return $this->hasOne(NewsletterSubscriber::class);
    }

    /**
     * Check if the member is part of the Executive Committee.
     */
    public function isExecutive(): bool
    {
        return $this->role === 'executive' || ! empty($this->ec_position);
    }

    /**
     * Check if the member is an Associate.
     */
    public function isAssociate(): bool
    {
        return $this->membership_category === 'associate' || $this->role === 'associate' || (int) $this->year_of_study === 0;
    }
}
