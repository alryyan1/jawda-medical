<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @method static \Illuminate\Database\Eloquent\Builder|ChildGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildGroup whereName($value)
 * @mixin \Eloquent
 */
class ChildGroup extends Model
{
    use HasFactory;
    protected $fillable = ['name'];
    public $timestamps = false;
}
