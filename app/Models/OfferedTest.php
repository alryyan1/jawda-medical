<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferedTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'main_test_id',
        'offer_id',
    ];

    /**
     * Get the offer that owns this offered test.
     */
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the main test that is offered.
     */
    public function mainTest()
    {
        return $this->belongsTo(MainTest::class);
    }
}
