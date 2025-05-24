<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabRequest extends Model
{
    use HasFactory;
    protected $table = 'labrequests'; // Explicitly define

    protected $fillable = [
        'main_test_id', 'pid', 'doctor_visit_id', 'hidden', 'is_lab2lab', 
        'valid', 'no_sample', 'price', 'amount_paid', 'discount_per', 
        'is_bankak', 'comment', 'user_requested', 'user_deposited', 
        'approve', 'endurance', 'is_paid', 'sample_id',
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
        'endurance' => 'decimal:2', // Cast to 2 decimal places
        'is_paid' => 'boolean',
    ];

    public function mainTest() { return $this->belongsTo(MainTest::class); }
    public function patient() { return $this->belongsTo(Patient::class, 'pid'); } // Explicit FK
    public function doctorVisit() { return $this->belongsTo(DoctorVisit::class); }
    public function requestingUser() { return $this->belongsTo(User::class, 'user_requested'); }
    public function depositUser() { return $this->belongsTo(User::class, 'user_deposited'); }
    
    // Relationship to results (one LabRequest can have many ChildTest results)
    public function results() { return $this->hasMany(RequestedResult::class); } 
    // RequestedResult model needs 'lab_request_id' FK
}