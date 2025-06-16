<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserDefaultShift extends Pivot
{
    protected $table = 'user_default_shifts';
    // public $incrementing = true; // If you gave it an ID primary key

    // Define relationships back to User and ShiftDefinition if needed directly from pivot
    // public function user() { return $this->belongsTo(User::class); }
    // public function shiftDefinition() { return $this->belongsTo(ShiftDefinition::class); }
}