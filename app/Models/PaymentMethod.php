<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether this method should count as "bank" in the legacy two-bucket
     * reports (every method other than cash is folded into the bank bucket).
     */
    public static function isBankLike(int $paymentMethodId): bool
    {
        return static::where('id', $paymentMethodId)->value('code') !== 'cash';
    }

    public static function defaultCashId(): int
    {
        return static::where('code', 'cash')->value('id');
    }
}
