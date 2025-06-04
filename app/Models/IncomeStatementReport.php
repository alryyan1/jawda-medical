<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $income_statement_id
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatementReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatementReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatementReport query()
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatementReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IncomeStatementReport whereIncomeStatementId($value)
 * @mixin \Eloquent
 */
class IncomeStatementReport extends Model
{
    use HasFactory;
}
