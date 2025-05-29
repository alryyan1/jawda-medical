<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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