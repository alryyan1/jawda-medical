<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <--- Ensure this is present
use Spatie\Permission\Traits\HasRoles;

/**
 * 
 *
 * @property int $id
 * @property string $username
 * @property mixed $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $doctor_id
 * @property bool $is_nurse
 * @property string $name
 * @property string $user_money_collector_type
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIsNurse($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUserMoneyCollectorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
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
        'is_supervisor',
        'is_active',
    ];

    // No guarded attributes on User for this change
    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime', // If you use email verification
        'password' => 'hashed',
        'is_nurse' => 'boolean',
        'is_supervisor' => 'boolean',
        'is_active' => 'boolean',
    ];
    
    public function defaultShifts(): BelongsToMany
    {
        return $this->belongsToMany(ShiftDefinition::class, 'user_default_shifts', 'user_id', 'shift_definition_id')
                    ->using(UserDefaultShift::class)
                    ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
    public function supervisedAttendances(): HasMany // Attendances this user supervised
    {
        return $this->hasMany(Attendance::class, 'supervisor_id');
    }

    public function recordedAttendances(): HasMany // Attendances this user recorded
    {
        return $this->hasMany(Attendance::class, 'recorded_by_user_id');
    }
}