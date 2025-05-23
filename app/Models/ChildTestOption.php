<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChildTestOption extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'child_test_id',
    ];
    public $timestamps = false;
    public function childTest()
    {
        return $this->belongsTo(ChildTest::class);
    }
}
