<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $finance_account_id
 * @property int $finance_entry_id
 * @property float $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry whereFinanceAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry whereFinanceEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DebitEntry whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DebitEntry extends Model
{
    use HasFactory;
}
