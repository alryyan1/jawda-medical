<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuggestedOrganism extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public $timestamps = false;

    /**
     * Get suggestions for any table
     */
    public static function getSuggestions($limit = 100)
    {
        return self::orderBy('name', 'asc')
                   ->limit($limit)
                   ->pluck('name')
                   ->toArray();
    }

    /**
     * Add or update suggestion (only if name is provided and not empty)
     */
    public static function addSuggestion($name)
    {
        if (empty(trim($name))) {
            return null;
        }

        $name = trim($name);
        
        // Check if suggestion already exists
        $existing = self::where('name', $name)->first();
        
        if ($existing) {
            return $existing;
        } else {
            // Create new suggestion
            return self::create(['name' => $name]);
        }
    }
}