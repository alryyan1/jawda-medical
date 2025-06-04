<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int $user_id
 * @property string $assets
 * @property string $obligations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement whereAssets($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement whereObligations($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BalanceSheetStatement whereUserId($value)
 * @mixin \Eloquent
 */
class BalanceSheetStatement extends Model
{
    use HasFactory;
}
