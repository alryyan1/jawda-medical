<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionRequestedService extends Model
{
    use HasFactory;

    protected $table = 'admission_requested_services';

    protected $fillable = [
        'admission_id',
        'service_id',
        'user_id',
        'user_deposited',
        'doctor_id',
        'price',
        'endurance',
        'discount',
        'discount_per',
        'count',
        'doctor_note',
        'nurse_note',
        'done',
        'approval',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'endurance' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_per' => 'integer',
        'count' => 'integer',
        'done' => 'boolean',
        'approval' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function requestingUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function depositUser()
    {
        return $this->belongsTo(User::class, 'user_deposited');
    }

    public function performingDoctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    /**
     * Get all the actual cost breakdown entries for this requested service.
     */
    public function costBreakdown()
    {
        return $this->hasMany(AdmissionRequestedServiceCost::class, 'admission_requested_service_id');
    }

    /**
     * Get all payment deposits made for this requested service.
     */
    public function deposits()
    {
        return $this->hasMany(AdmissionRequestedServiceDeposit::class, 'admission_requested_service_id');
    }

    public function totalDepositsBank()
    {
        return $this->deposits()->where('is_bank', 1)->sum('amount');
    }

    public function totalDepositsCash()
    {
        return $this->deposits()->where('is_bank', 0)->sum('amount');
    }

    public function requestedServiceCosts()
    {
        return $this->hasMany(AdmissionRequestedServiceCost::class, 'admission_requested_service_id');
    }

    // Accessors

    public function getTotalPriceAttribute(): float
    {
        return (float) $this->price * (int) ($this->count ?? 1);
    }

    /**
     * Calculates the net amount payable by the patient after discounts and company endurance.
     */
    public function getNetPayableByPatientAttribute(): float
    {
        $totalPrice = $this->total_price; // Uses accessor

        $discountAmountFixed = (float) $this->discount;
        $discountAmountPercentage = ($totalPrice * (int)($this->discount_per ?? 0)) / 100;
        $totalDiscount = $discountAmountFixed + $discountAmountPercentage;

        $amountAfterDiscount = $totalPrice - $totalDiscount;

        $enduranceAmount = (float) $this->endurance;

        return $amountAfterDiscount - $enduranceAmount;
    }

    public function getBalanceAttribute(): float
    {
        // Balance is now calculated at admission level, not service level
        // This method is kept for backward compatibility but returns net_payable_by_patient
        return $this->net_payable_by_patient;
    }

    public function addRequestedServiceCosts()
    {
        // Use price for cost calculation (no payment logic at service level)
        $amount_paid = $this->price;
        //**@var ServiceCost $cost  */
        foreach ($this->service->service_costs as $cost) {
            $amount = 0;
            if ($cost->cost_type == 'after cost') {
                //نجيب اجمالي مجموع المصروف في نفس  الخدمه دي يعني نجمع كل مصروفاتها كم ما عدا الحاليه دي
                $totalCost = 0;

                foreach ($cost->costOrders as $co) {
                    $c = ServiceCost::where('sub_service_cost_id', $co->sub_service_cost_id)->where('service_id', $this->service->id)->first();

                    if ($c->fixed > 0) {
                        $totalCost += $c->fixed * $this->count;
                    } else {
                        $totalCost += ($amount_paid * $c->percentage / 100) * $this->count;
                    }
                }
                $paid_net = $amount_paid - $totalCost;
                $amount += ($paid_net * $cost->percentage / 100) * $this->count;
            } else {
                if ($cost->fixed > 0) {
                    $amount += $cost->fixed * $this->count;
                } else {
                    $amount += ($amount_paid * $cost->percentage / 100) * $this->count;
                }
            }

            AdmissionRequestedServiceCost::create([
                'admission_requested_service_id' => $this->id,
                'service_cost_id' => $cost->id,
                'sub_service_cost_id' => $cost->sub_service_cost_id,
                'amount' => $amount
            ]);
        }
    }

    //يتم احتساب مصروفات الخدمه عشان يتم خصمها من الطبيب
    //يتم احستاب المصروفات علي حسب ما تم اختياره من مصروفات الطبيب
    public function getTotalCostsForDoctor($doctor)
    {
        $totalCost = 0;
        /**@var AdmissionRequestedServiceCost $requestedServiceCost */
        foreach ($this->requestedServiceCosts as $requestedServiceCost) {
            $exists = $doctor->doctorServiceCosts->contains(function ($dc) use ($requestedServiceCost) {
                return $dc->sub_service_cost_id == $requestedServiceCost->sub_service_cost_id;
            });

            if ($exists) {
                $totalCost += $requestedServiceCost->amount;
            }
        }
        return $totalCost;
    }

    public function getTotalCosts($doctor)
    {
        $totalCost = 0;
        /**@var AdmissionRequestedServiceCost $requestedServiceCost */
        foreach ($this->requestedServiceCosts as $requestedServiceCost) {
            $totalCost += $requestedServiceCost->amount;
        }
        return $totalCost;
    }

    public function updateRequestedServiceCosts()
    {
        // Use price for cost calculation (no payment logic at service level)
        $amount_paid = $this->price;
        //**@var requestedServiceCosts $requestedServiceCost  */
        foreach ($this->requestedServiceCosts as $requestedServiceCost) {
            $amount = 0;
            $cost = $requestedServiceCost->serviceCost;
            if ($cost->cost_type == 'after cost') {
                //نجيب اجمالي مجموع المصروف في نفس  الخدمه دي يعني نجمع كل مصروفاتها كم ما عدا الحاليه دي
                $totalCost = 0;
                /**@var CostOrder $co */
                foreach ($cost->costOrders as $co) {
                    $c = $this->requestedServiceCosts()->where('sub_service_cost_id', $co->sub_service_cost_id)->first();

                    $totalCost += $c->amount;
                }
                $paid_net = $amount_paid - $totalCost;
                $amount += ($paid_net * $cost->percentage / 100) * $this->count;
                $requestedServiceCost->update(['amount' => $amount]);
            }
        }
    }

}
