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
        'year_of_study',
        'ministry',
        'status',
        'admin_notes',
        'role',
        'joined_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'joined_at' => 'datetime',
        'password' => 'hashed',
    ];

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
}
