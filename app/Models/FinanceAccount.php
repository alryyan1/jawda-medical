<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'debit', // 'debit' or 'credit'
        'description',
        'code',
        'type', // 'revenue' or 'cost'
    ];

    // If you are using PHP 8.1+ Enums for 'debit' and 'type', cast them:
    // protected $casts = [
    //     'debit' => DebitCreditEnum::class,
    //     'type' => AccountTypeEnum::class,
    // ];

    // Relationships to Doctors (a FinanceAccount can be linked to multiple doctors
    // in different roles, e.g., main account or insurance account)
    public function doctorsAsMainAccount()
    {
        return $this->hasMany(Doctor::class, 'finance_account_id');
    }

    public function doctorsAsInsuranceAccount()
    {
        return $this->hasMany(Doctor::class, 'finance_account_id_insurance');
    }
}