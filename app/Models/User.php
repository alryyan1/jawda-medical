<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <--- Ensure this is present
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable,HasRoles; // <--- And here

    protected $fillable = [
        'name',
        'username', // We added this in the migration
        'password',
        // 'email', // If you decide to add email later
        // Add other fields from your users table migration as needed for registration/profile
        'doctor_id',
        'is_nurse',
        'user_money_collector_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime', // If you use email verification
        'password' => 'hashed',
        'is_nurse' => 'boolean',
    ];
}