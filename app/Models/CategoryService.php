<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CategoryService extends Pivot
{
    use HasFactory;

    protected $table = 'category_services';

    protected $fillable = [
        'category_id',
        'service_id',
        'percentage',
        'fixed',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed' => 'decimal:2',
    ];

    public $timestamps = true;

    /**
     * Get the category that owns this service.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the service.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
