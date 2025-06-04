<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $date
 * @property string $amount
 * @property string $beneficiary
 * @property string|null $description
 * @property string|null $pdf_file
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $finance_entry_id
 * @property int $user_id
 * @property string $signature_file_name
 * @property string $phone
 * @property int|null $user_approved
 * @property int|null $financial_auditor
 * @property string|null $user_approved_time
 * @property string|null $auditor_approved_time
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission query()
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereAuditorApprovedTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereBeneficiary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereFinanceEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereFinancialAuditor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission wherePdfFile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereSignatureFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereUserApproved($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereUserApprovedTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PettyCashPermission whereUserId($value)
 * @mixin \Eloquent
 */
class PettyCashPermission extends Model
{
    use HasFactory;
}
