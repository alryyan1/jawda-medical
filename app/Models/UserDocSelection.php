<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDocSelection extends Model
{
    use HasFactory;

    protected $table = 'user_doc_selections';

    // Define composite primary key
    protected $primaryKey = ['user_id', 'doc_id'];
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'doc_id',
        'active',
        'fav_service',
    ];

    protected $casts = [
        'active' => 'boolean',
        'user_id' => 'integer',
        'doc_id' => 'integer',
        'fav_service' => 'integer',
    ];

    // Disable timestamps since the table doesn't have them
    public $timestamps = false;

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doc_id');
    }
}
