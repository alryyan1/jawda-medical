<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\LabRequest
 *
 * @property int $id
 * @property int $main_test_id
 * @property int $pid Patient ID
 * @property int $doctor_visit_id
 * @property bool $hidden
 * @property bool $is_lab2lab
 * @property bool $valid
 * @property bool $no_sample
 * @property string|null $price
 * @property int $count Default 1
 * @property string|null $amount_paid
 * @property int $discount_per
 * @property bool $is_bankak Payment method for this request
 * @property string|null $comment Main comment for the lab request
 * @property int|null $user_requested User who made the request
 * @property int|null $user_deposited User who handled payment
 * @property bool $approve Overall approval/authorization status of the lab request
 * @property string $endurance Amount covered by insurance/company
 * @property bool $is_paid Payment status
 * @property string|null $sample_id Unique ID for the sample
 * @property string $result_status Status of the results (e.g., pending_sample, results_complete_pending_auth, authorized)
 * @property int|null $authorized_by_user_id User who authorized this lab request
 * @property \Illuminate\Support\Carbon|null $authorized_at Timestamp of authorization
 * @property int|null $payment_shift_id Shift under which payment was recorded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $depositUser
 * @property-read \App\Models\DoctorVisit|null $doctorVisit
 * @property-read \App\Models\MainTest $mainTest
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\User|null $requestingUser
 * @property-read \App\Models\User|null $authorizedBy
 * @property-read \App\Models\Shift|null $paymentShift
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestedResult> $results
 * @property-read int|null $results_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestedOrganism> $requestedOrganisms
 * @property-read int|null $requested_organisms_count
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest query()
 * @mixin \Eloquent
 */
class LabRequest extends Model
{
    use HasFactory;
    protected $table = 'labrequests';

    protected $fillable = [
        'main_test_id',
        'pid',
        'doctor_visit_id',
        'hidden',
        'is_lab2lab',
        'valid',
        'no_sample',
        'price',
        'count', // Added count
        'amount_paid',
        'discount_per',
        'is_bankak',
        'comment',
        'user_requested',
        'user_deposited',
        'approve', // Overall authorization flag for the request
        'endurance',
        'is_paid',
        'sample_id',
        'result_status',         // NEW
        'authorized_by_user_id', // NEW
        'authorized_at',         // NEW
        'payment_shift_id',      // NEW
    ];

    protected $casts = [
        'hidden' => 'boolean',
        'is_lab2lab' => 'boolean',
        'valid' => 'boolean',
        'no_sample' => 'boolean',
        'price' => 'decimal:2',
        'count' => 'integer',
        'amount_paid' => 'decimal:2',
        'discount_per' => 'integer',
        'is_bankak' => 'boolean',
        'approve' => 'boolean',
        'endurance' => 'decimal:2',
        'is_paid' => 'boolean',
        'result_status' => 'string',
        'authorized_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function mainTest(): BelongsTo
    {
        return $this->belongsTo(MainTest::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'pid');
    }

    

    public function requestingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_requested');
    }

    public function depositUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_deposited');
    }

    public function results(): HasMany
    {
        return $this->hasMany(RequestedResult::class);
    }

    public function requestedOrganisms(): HasMany
    {
        return $this->hasMany(RequestedOrganism::class);
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function paymentShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'payment_shift_id');
    }

    public function doctorVisit(): BelongsTo
    {
        return $this->belongsTo(DoctorVisit::class); // Ensure DoctorVisit class is imported
    }
    /**
     * Check if this lab request and all its expected child test results
     * have been marked as authorized.
     *
     * This is a conceptual implementation. The exact logic depends on your business rules
     * for what constitutes "fully authorized".
     *
     * @return bool
     */
    public function isFullyAuthorized(): bool
    {
        // Option 1: Simple check on the LabRequest's own 'approve' flag
        if ($this->approve) {
            // Additionally, you might want to ensure all results are entered if it's a divided test
            if ($this->mainTest && $this->mainTest->divided) {
                $expectedChildTestsCount = $this->mainTest->childTests()->count();
                $enteredResultsCount = $this->results()->where(fn($q) => $q->whereNotNull('result')->where('result', '!=', ''))->count();
                return $enteredResultsCount >= $expectedChildTestsCount;
            }
            return true; // Approved and not divided, or divided and all results seem entered
        }
        return false;

        // Option 2: More granular check (if you re-introduce authorization per RequestedResult)
        // This would involve checking if $this->approve is true AND all $this->results have authorized_at set.
        /*
        if (!$this->approve) {
            return false;
        }
        if ($this->mainTest && $this->mainTest->divided) {
            $expectedChildTestIds = $this->mainTest->childTests()->pluck('id');
            if ($expectedChildTestIds->isEmpty()) {
                return true; // No child tests to authorize, so main approval is enough
            }
            $authorizedChildResultsCount = $this->results()
                ->whereIn('child_test_id', $expectedChildTestIds)
                ->whereNotNull('authorized_at') // Assuming you add this field back to RequestedResult
                ->count();
            return $authorizedChildResultsCount >= $expectedChildTestIds->count();
        }
        return true; // Not divided, main approval is enough
        */
    }

    /**
     * Generates a unique sample ID.
     * This is a basic example. You might want a more robust, centralized sequence.
     * @return string
     */
    public static function generateSampleId(DoctorVisit $visit = null): string
    {
        $prefix = $visit ? "V{$visit->id}-" : "S-";
        return $prefix . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
    }
}