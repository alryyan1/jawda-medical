<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceChildTestNormalRange extends Model
{
    use HasFactory;
    protected $table = 'child_test_devices'; // Matches your table name
    protected $fillable = ['child_test_id', 'device_id', 'normal_range'];
    public $timestamps = false; // As per your table schema

    public function childTest()
    {
        return $this->belongsTo(ChildTest::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}