<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'cash_percentage',
        'company_percentage',
        'static_wage',
        'lab_percentage',
        'specialist_id',
        'start', // This was an INT(11) in the schema, meaning? Patient capacity? Starting number?
        'image',
        'finance_account_id',
        'finance_account_id_insurance', // Corrected name from migration
        'calc_insurance',
    ];

    protected $casts = [
        'cash_percentage' => 'decimal:2',
        'company_percentage' => 'decimal:2',
        'static_wage' => 'decimal:2',
        'lab_percentage' => 'decimal:2',
        'calc_insurance' => 'boolean',
        // 'start' => 'integer', // If it's just a number
    ];

    /**
     * Get the specialist that owns the doctor.
     */
    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }

    /**
     * Get the finance account for the doctor.
     */
    public function financeAccount()
    {
        // Assuming 'finance_account_id' is the FK column name
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    /**
     * Get the insurance finance account for the doctor.
     */
    public function insuranceFinanceAccount()
    {
        // Assuming 'finance_account_id_insurance' is the FK column name
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id_insurance');
    }

    /**
     * Get the users associated with this doctor (if a doctor can be a user).
     * Or, if a user *has one* doctor profile.
     */
    public function user()
    {
        // If a User has a doctor_id, then a Doctor hasOne User (acting as that doctor)
        return $this->hasOne(User::class);
        // Or if a Doctor can have multiple user accounts (less common for this field name)
        // return $this->hasMany(User::class);
    }
}