<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'service_group_id',
        'price',
        'activate',
        'variable',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'activate' => 'boolean',
        'variable' => 'boolean',
    ];

    public function serviceGroup()
    {
        return $this->belongsTo(ServiceGroup::class);
    }
}