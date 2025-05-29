<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // Your 'cost_categories' table schema does not have timestamps by default
    public $timestamps = false; 

    /**
     * Get the costs associated with this category.
     */
    public function costs()
    {
        return $this->hasMany(Cost::class);
    }
}