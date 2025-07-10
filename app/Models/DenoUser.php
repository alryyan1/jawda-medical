<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class DenoUser extends Model
{
    use HasFactory;
    protected $table = 'denos_users';
    protected $fillable = ['user_id', 'shift_id', 'deno_id', 'count'];
}