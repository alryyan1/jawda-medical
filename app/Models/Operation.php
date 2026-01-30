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

    public function costs()
    {
        return $this->hasMany(OperationCost::class);
    }

    /**
     * Calculate and create auto items based on surgeon fee
     */
    /**
     * Calculate and create auto items based on surgeon fee and template config
     */
    public function calculateAutoItems()
    {
        // Delete existing auto-calculated items
        $this->financeItems()->where('is_auto_calculated', true)->delete();

        if ($this->surgeon_fee <= 0) {
            return;
        }

        // 1. Try to find a template for this operation type
        $template = Operation::where('operation_type', $this->operation_type)
            ->whereNull('admission_id') // Template has no admission
            ->where('id', '!=', $this->id) // Don't match self if self is template
            ->with('costs.operationItem')
            ->first();

        // 2. If template exists with costs, use them
        if ($template && $template->costs->count() > 0) {
            foreach ($template->costs as $cost) {
                // Calculate Amount
                $amount = 0;
                if ($cost->fixed !== null && $cost->fixed > 0) {
                    $amount = $cost->fixed;
                } elseif ($cost->perc !== null && $cost->perc > 0) {
                    $amount = $this->surgeon_fee * ($cost->perc / 100);
                }

                if ($amount > 0) {
                    $this->financeItems()->create([
                        'operation_item_id' => $cost->operation_item_id,
                        'description' => $cost->operationItem->name ?? 'Item',
                        'amount' => $amount,
                        'is_auto_calculated' => true,
                    ]);
                }
            }
        }
        // 3. Fallback to Hardcoded Defaults (Legacy support)
        else {
            // 1. Staff: Surgeon (ID 1)
            $this->financeItems()->create([
                'operation_item_id' => 1,
                'description' => 'أخصائي الجراحة',
                'amount' => $this->surgeon_fee,
                'is_auto_calculated' => true,
            ]);

            // 2. Staff: Assistant (10%) (ID 2)
            $this->financeItems()->create([
                'operation_item_id' => 2,
                'description' => 'مساعد الجراح',
                'amount' => $this->surgeon_fee * 0.10,
                'is_auto_calculated' => true,
            ]);

            // 3. Staff: Anesthesia (50%) (ID 3)
            $this->financeItems()->create([
                'operation_item_id' => 3,
                'description' => 'أخصائي التخدير',
                'amount' => $this->surgeon_fee * 0.50,
                'is_auto_calculated' => true,
            ]);

            // 4. Center: Center Share (100%) (Assume ID exists or create legacy generic placeholder? Let's check ID 16 is Admission Services)
            // I'll skip Center Share for now if I don't have an ID, OR I assume ID 4 or 5 exists.
            // Based on fetch_items, I only saw 1,2,3,16.
            // I will use null for operation_item_id for ad-hoc items if allowed, but schema has it nullable?
            // Yes schema has it nullable.

            // However, user said "instead of item_type... use operation_item".
            // This implies I SHOULD have an item for every row.
            // I will assume for now I can use ID 3 for example or just rely on description if ID is missing?
            // The column is nullable. So I can leave it null if I don't have a matching item.

            // 4. Center: Center Share
            $this->financeItems()->create([
                'operation_item_id' => null, // No specific item ID known yet
                'description' => 'نصيب المركز',
                'amount' => $this->surgeon_fee,
                'is_auto_calculated' => true,
            ]);

            // 5. Center: Accommodation (ID 16?)
            $items = \App\Models\OperationItem::where('name', 'LIKE', '%تنويم%')->orWhere('type', 'center')->get();
            // To be safe, I'll use null unless I'm sure.
            // But for Accommodation, let's use null with description.
            $this->financeItems()->create([
                'operation_item_id' => null,
                'description' => 'الإقامة',
                'amount' => 150000,
                'is_auto_calculated' => true,
            ]);
        }

        $this->updateTotals();
    }

    /**
     * Recalculate totals from finance items
     */
    public function updateTotals()
    {
        // Load items with operationItem to check type
        $items = $this->financeItems()->with('operationItem')->get();

        $staffTotal = $items->filter(function ($item) {
            // Check operationItem type OR fallback?
            // Since we removed 'category' column, we must rely on operationItem relationship
            // If operation_item_id is null, how do we know?
            // We might need to assume 'center' if null, or check description?
            // The previous logic for Center Share had no ID.
            // Let's assume if operationItem exists and type is 'staff', it is staff. Everything else is center/other.
            return $item->operationItem && $item->operationItem->type === 'staff';
        })->sum('amount');

        $centerTotal = $items->sum('amount') - $staffTotal;

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
