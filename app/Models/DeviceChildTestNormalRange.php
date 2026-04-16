<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceChildTestNormalRange extends Model
{
    use HasFactory;

    protected $table = 'child_test_devices';
    protected $fillable = ['child_test_id', 'device_id', 'normal_range', 'is_default', 'user_id'];
    public $timestamps = true;

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function childTest()
    {
        return $this->belongsTo(ChildTest::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
