<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    use HasFactory;
    protected $fillable = ['container_name'];
    public $timestamps = false; // As per your migration for containers

    public function mainTests()
    {
        return $this->hasMany(MainTest::class);
    }
}