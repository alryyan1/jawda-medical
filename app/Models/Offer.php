<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Get the tests associated with this offer.
     */
    public function offeredTests()
    {
        return $this->hasMany(OfferedTest::class);
    }

    /**
     * Get the main tests through the pivot table.
     */
    public function mainTests()
    {
        return $this->belongsToMany(MainTest::class, 'offered_tests', 'offer_id', 'main_test_id')
            ->withPivot('price');
    }
}
