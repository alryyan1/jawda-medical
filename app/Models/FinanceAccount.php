<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int $account_category_id
 * @property string $debit
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $code
 * @property string|null $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Doctor> $doctorsAsInsuranceAccount
 * @property-read int|null $doctors_as_insurance_account_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Doctor> $doctorsAsMainAccount
 * @property-read int|null $doctors_as_main_account_count
 * @method static \Database\Factories\FinanceAccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereAccountCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceAccount whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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