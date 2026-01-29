<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'operation_date',
        'operation_time',
        'operation_type',
        'description',
        'surgeon_fee',
        'total_staff',
        'total_center',
        'total_amount',
        'cash_paid',
        'bank_paid',
        'bank_receipt_image',
        'notes',
        'status',
        'user_id',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'operation_time' => 'datetime',
        'surgeon_fee' => 'decimal:2',
        'total_staff' => 'decimal:2',
        'total_center' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cash_paid' => 'decimal:2',
        'bank_paid' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function financeItems()
    {
        return $this->hasMany(OperationFinanceItem::class);
    }

    /**
     * Calculate and create auto items based on surgeon fee
     */
    public function calculateAutoItems()
    {
        // Delete existing auto-calculated items
        $this->financeItems()->where('is_auto_calculated', true)->delete();

        if ($this->surgeon_fee <= 0) {
            return;
        }

        // Surgeon (staff)
        $this->financeItems()->create([
            'item_type' => 'surgeon',
            'category' => 'staff',
            'description' => 'أخصائي جراحة',
            'amount' => $this->surgeon_fee,
            'is_auto_calculated' => true,
        ]);

        // Assistant = 10% of surgeon (staff)
        $this->financeItems()->create([
            'item_type' => 'assistant',
            'category' => 'staff',
            'description' => 'مساعد جراح',
            'amount' => $this->surgeon_fee * 0.10,
            'is_auto_calculated' => true,
        ]);

        // Anesthesia = 50% of surgeon (staff)
        $this->financeItems()->create([
            'item_type' => 'anesthesia',
            'category' => 'staff',
            'description' => 'أخصائي تخدير',
            'amount' => $this->surgeon_fee * 0.50,
            'is_auto_calculated' => true,
        ]);

        // Center share = 100% of surgeon (center)
        $this->financeItems()->create([
            'item_type' => 'center_share',
            'category' => 'center',
            'description' => 'نصيب المركز',
            'amount' => $this->surgeon_fee,
            'is_auto_calculated' => true,
        ]);

        $this->updateTotals();
    }

    /**
     * Recalculate totals from finance items
     */
    public function updateTotals()
    {
        $staffTotal = $this->financeItems()->where('category', 'staff')->sum('amount');
        $centerTotal = $this->financeItems()->where('category', 'center')->sum('amount');

        $this->update([
            'total_staff' => $staffTotal,
            'total_center' => $centerTotal,
            'total_amount' => $staffTotal + $centerTotal,
        ]);
    }

    /**
     * Get balance remaining
     */
    public function getBalanceAttribute()
    {
        return $this->total_amount - ($this->cash_paid + $this->bank_paid);
    }
}
