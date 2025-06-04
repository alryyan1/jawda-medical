<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $description
 * @property int $transfer
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $doctor_shift_id
 * @property int|null $user_created
 * @property int $is_net
 * @property int|null $user_net
 * @property int|null $shift_id
 * @property string $file_name
 * @property int $cancel
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereCancel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereDoctorShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereIsNet($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereTransfer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereUserCreated($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FinanceEntry whereUserNet($value)
 * @mixin \Eloquent
 */
class FinanceEntry extends Model
{
    use HasFactory;
}
