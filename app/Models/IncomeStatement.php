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
 * @property string $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatement whereUserId($value)
 * @mixin \Eloquent
 */
class IncomeStatement extends Model
{
    use HasFactory;
}
