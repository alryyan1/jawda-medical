<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'ward_id',
        'bed_id',
        'admission_date',
        'admission_days',
        'admission_purpose',
        'discharge_date',
        'admission_reason',
        'diagnosis',
        'status',
        'doctor_id',
        'specialist_doctor_id',
        'user_id',
        'notes',
        'provisional_diagnosis',

        'medical_history',
        'current_medications',
        'referral_source',
        'expected_discharge_date',
        'next_of_kin_name',
        'next_of_kin_relation',
        'next_of_kin_phone',
    ];

    protected $casts = [
        'admission_date' => 'datetime',
        'discharge_date' => 'datetime',
        'expected_discharge_date' => 'date',
    ];

    /**
     * Get the patient that owns the admission.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the ward for the admission.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Room is derivable via bed: $admission->bed->room
     */

    /**
     * Get the bed for the admission.
     */
    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }


    /**
     * Get the doctor for the admission.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the specialist doctor for the admission.
     */
    public function specialistDoctor()
    {
        return $this->belongsTo(Doctor::class, 'specialist_doctor_id');
    }

    /**
     * Get the user who created the admission.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active admissions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'admitted');
    }

    /**
     * Scope a query to only include discharged admissions.
     */
    public function scopeDischarged($query)
    {
        return $query->where('status', 'discharged');
    }

    /**
     * Get the requested services for the admission.
     */
    public function requestedServices()
    {
        return $this->hasMany(AdmissionRequestedService::class);
    }

    /**
     * Get the requested surgeries for the admission.
     */
    public function requestedSurgeries()
    {
        return $this->hasMany(RequestedSurgery::class);
    }

    /**
     * Get the requested lab tests for the admission.
     */
    public function requestedLabTests()
    {
        return $this->hasMany(AdmissionRequestedLabTest::class);
    }

    /**
     * Get the vital signs for the admission.
     */
    public function vitalSigns()
    {
        return $this->hasMany(AdmissionVitalSign::class);
    }

    /**
     * Get all transactions for the admission.
     */
    public function transactions()
    {
        return $this->hasMany(AdmissionTransaction::class);
    }



    /**
     * Get the treatments for the admission.
     */
    public function treatments()
    {
        return $this->hasMany(AdmissionTreatment::class);
    }

    /**
     * Get the doses for the admission.
     */
    public function doses()
    {
        return $this->hasMany(AdmissionDose::class);
    }

    /**
     * Get the nursing assignments for the admission.
     */
    public function nursingAssignments()
    {
        return $this->hasMany(AdmissionNursingAssignment::class);
    }

    /**
     * Calculate the number of days the patient has been admitted.
     * Uses new calculation logic based on admission time:
     * - Morning period (7:00 AM - 12:00 PM): 24-hour system
     * - Evening period (1:00 PM - 6:00 AM next day): Full day at 12:00 PM
     */
    public function getDaysAdmittedAttribute()
    {
        // admission_date is already a Carbon instance from casts
        $admissionDateTime = $this->admission_date instanceof Carbon
            ? $this->admission_date->copy()
            : Carbon::parse($this->admission_date);

        // Handle discharge_date
        if ($this->discharge_date) {
            $dischargeDateTime = $this->discharge_date instanceof Carbon
                ? $this->discharge_date->copy()
                : Carbon::parse($this->discharge_date);
        } else {
            $dischargeDateTime = Carbon::now();
        }

        $admissionHour = (int) $admissionDateTime->format('H');

        // نظام الـ 24 ساعة (الدخول الصباحي): 7:00 ص - 12:00 ظ
        // إذا دخل المريض من 7:00 ص إلى 12:00 ظ، يومه ينتهي في نفس التوقيت من اليوم التالي (24 ساعة كاملة)
        // مثال: دخل 9:00 ص، ينتهي 9:00 ص اليوم التالي
        if ($admissionHour >= 7 && $admissionHour < 12) {
            // احسب الفرق بالساعات بين وقت الدخول ووقت الخروج
            $hours = $admissionDateTime->diffInHours($dischargeDateTime, false);

            // إذا كانت المدة أقل من 24 ساعة، احسبها كيوم واحد
            if ($hours < 24) {
                return 1;
            }

            // احسب الأيام بناءً على الساعات (كل 24 ساعة = يوم واحد)
            // استخدم ceil لضمان احتساب أي جزء من اليوم كيوم كامل
            $days = ceil($hours / 24.0);

            return max(1, $days);
        }

        // نظام اليوم الكامل (الدخول المسائي/المتأخر): 1:00 ظ - 6:00 ص اليوم التالي
        // إذا دخل المريض من 1:00 ظ إلى 6:00 ص اليوم التالي، يومه ينتهي حكماً عند 12:00 ظ من اليوم التالي
        // مثال: دخل 4:00 عصراً أو 3:00 فجراً، عند 12:00 ظ يُعتبر أتم يوماً كاملاً
        if ($admissionHour >= 13 || $admissionHour < 6) {
            // حدد نقطة البداية للاحتساب (12:00 ظ)
            $noonOfAdmissionDay = $admissionDateTime->copy()->setTime(12, 0, 0);

            // إذا كان الدخول قبل 12:00 ظ من نفس اليوم (من 1:00 فجراً إلى 6:00 ص)
            if ($admissionHour < 6) {
                // ابدأ من 12:00 ظ من نفس يوم الدخول
                $startDate = $noonOfAdmissionDay;
            } else {
                // إذا كان الدخول بعد 12:00 ظ (من 1:00 ظ فما بعد)، ابدأ من 12:00 ظ من اليوم التالي
                $startDate = $admissionDateTime->copy()->addDay()->setTime(12, 0, 0);
            }

            // حدد نقطة النهاية للاحتساب (12:00 ظ)
            $dischargeHour = (int) $dischargeDateTime->format('H');
            $noonOfDischargeDay = $dischargeDateTime->copy()->setTime(12, 0, 0);

            // إذا كان الخروج قبل 12:00 ظ من يوم الخروج
            if ($dischargeDateTime->lt($noonOfDischargeDay)) {
                // احسب حتى 12:00 ظ من اليوم السابق (لأنه لم يصل لـ 12:00 ظ من يوم الخروج)
                $endDate = $dischargeDateTime->copy()->subDay()->setTime(12, 0, 0);
            } else {
                // إذا كان الخروج بعد 12:00 ظ، احسب حتى 12:00 ظ من نفس يوم الخروج
                $endDate = $noonOfDischargeDay;
            }

            // احسب عدد الأيام بين نقطة البداية ونقطة النهاية
            // كل مرة نصل لـ 12:00 ظ = يوم كامل
            $days = $startDate->diffInDays($endDate);

            // إذا كانت نقطة البداية بعد نقطة النهاية أو في نفس اليوم، فهذا يعني يوم واحد على الأقل
            if ($startDate->gte($endDate)) {
                return 1;
            }

            // أضف يوم واحد لأننا نحسب من 12:00 ظ إلى 12:00 ظ (يوم كامل)
            return max(1, $days + 1);
        }

        // الحالة الافتراضية (6:00 ص - 7:00 ص): احسب بنفس الطريقة القديمة
        $days = $admissionDateTime->diffInDays($dischargeDateTime);
        return max(1, $days + 1);
    }

    /**
     * Calculate the total balance for the admission.
     * Balance = Total Credits - Total Debits
     */
    public function getBalanceAttribute()
    {
        $totalCredits = (float) $this->transactions()->where('type', 'credit')->sum('amount');
        $totalDebits = (float) $this->transactions()->where('type', 'debit')->sum('amount');

        return $totalCredits - $totalDebits;
    }
}
