<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $main_test_id
 * @property int $pid
 * @property bool $hidden
 * @property bool $is_lab2lab
 * @property bool $valid
 * @property bool $no_sample
 * @property string|null $price
 * @property string|null $amount_paid
 * @property int $discount_per
 * @property bool $is_bankak
 * @property string|null $comment
 * @property int|null $user_requested
 * @property int|null $user_deposited
 * @property bool $approve
 * @property string $endurance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_paid
 * @property string|null $sample_id Unique ID for the sample collected for this test
 * @property int $doctor_visit_id
 * @property-read \App\Models\User|null $depositUser
 * @property-read \App\Models\DoctorVisit|null $doctorVisit
 * @property-read \App\Models\MainTest $mainTest
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\User|null $requestingUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestedResult> $results
 * @property-read int|null $results_count
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereApprove($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereDiscountPer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereDoctorVisitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereIsBankak($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereIsLab2lab($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereMainTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereNoSample($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest wherePid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereSampleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereUserDeposited($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereUserRequested($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LabRequest whereValid($value)
 * @mixin \Eloquent
 */
class LabRequest extends Model
{
    use HasFactory;
    protected $table = 'labrequests';

    protected $fillable = [
        'main_test_id', 'pid', 'doctor_visit_id', 'hidden', 'is_lab2lab', 
        'valid', 'no_sample', 'price', 'amount_paid', 'discount_per', 
        'is_bankak', 'comment', 'user_requested', 'user_deposited', 
        'approve', 'endurance', 'is_paid', 'sample_id', 'count',
        'result_status'
    ];

    protected $casts = [
        'hidden' => 'boolean',
        'is_lab2lab' => 'boolean',
        'valid' => 'boolean',
        'no_sample' => 'boolean',
        'price' => 'decimal:1',
        'amount_paid' => 'decimal:1',
        'discount_per' => 'integer',
        'is_bankak' => 'boolean',
        'approve' => 'boolean',
        'endurance' => 'decimal:2',
        'is_paid' => 'boolean',
        'count' => 'integer',
        'result_status' => 'string'
    ];

    public function mainTest() { return $this->belongsTo(MainTest::class); }
    public function patient() { return $this->belongsTo(Patient::class, 'pid'); }
    public function doctorVisit() { return $this->belongsTo(DoctorVisit::class); }
    public function requestingUser() { return $this->belongsTo(User::class, 'user_requested'); }
    public function depositUser() { return $this->belongsTo(User::class, 'user_deposited'); }
    public function results() { return $this->hasMany(RequestedResult::class); }

    /**
     * Check if all expected child test results for this lab request
     * have been entered and authorized.
     *
     * @return bool
     */
    public function isFullyAuthorized(): bool
    {
        // 1. Get the MainTest associated with this LabRequest
        $mainTest = $this->mainTest; // Assumes mainTest relationship is loaded or will be loaded

        if (!$mainTest) {
            // This LabRequest is invalid or data is missing, cannot determine authorization
            return false; 
        }

        // 2. Get all ChildTest IDs expected for this MainTest
        // Ensure the childTests relationship is efficient or IDs are cached if performance is an issue
        $expectedChildTestIds = $mainTest->childTests()->pluck('id');

        if ($expectedChildTestIds->isEmpty()) {
            // If the MainTest has no defined ChildTests, what does "authorized" mean?
            // Option A: Consider it authorized by default (if the MainTest itself can be authorized)
            // Option B: Consider it not authorizable if no components (depends on workflow)
            // Option C: Check an 'is_authorized' flag directly on the LabRequest model for such cases.
            // For now, let's assume if no child tests, it's not "fully authorized" in terms of child results.
            // Or, if the main test itself has a result field not tied to child tests, check that.
            // This depends on whether a MainTest can have a result without child components.
            // If main test requires authorization even without children, check labrequest.approve flag
            return (bool) $this->approve && $this->results()->doesntExist(); // Example: approved and no child results expected/entered
        }

        // 3. Get all RequestedResult records for this LabRequest that are authorized
        $authorizedResultsForThisRequest = $this->results()
            ->whereIn('child_test_id', $expectedChildTestIds)
            ->whereNotNull('result') // Ensure a result value is present
            ->whereNotNull('authorized_at') // And it has been authorized
            ->pluck('child_test_id'); // Get the IDs of authorized child tests

        // 4. Compare: Are all expected child tests present in the authorized results?
        //    And is the count of authorized results equal to the count of expected child tests?
        $allExpectedAreAuthorized = $expectedChildTestIds->diff($authorizedResultsForThisRequest)->isEmpty();
        $correctCountAuthorized = $expectedChildTestIds->count() === $authorizedResultsForThisRequest->count();

        return $allExpectedAreAuthorized && $correctCountAuthorized;
    }

    /**
     * A simpler check if just the `approve` flag on the LabRequest itself signifies overall authorization.
     * This is less granular than checking individual child test result authorizations.
     */
    // public function isMarkedAsApproved(): bool
    // {
    //     return (bool) $this->approve;
    // }
}