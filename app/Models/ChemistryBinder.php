<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChemistryBinder extends Model
{
    public $timestamps = false;
    protected $table = 'chemistry_bindings';
    protected $guarded = [];
    use HasFactory;
}
