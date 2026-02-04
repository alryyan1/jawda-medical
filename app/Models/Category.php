<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the services associated with this category.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'category_services')
            ->using(CategoryService::class)
            ->withPivot(['id', 'percentage', 'fixed'])
            ->withTimestamps();
    }

    /**
     * Get the doctors assigned to this category.
     */
    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}
