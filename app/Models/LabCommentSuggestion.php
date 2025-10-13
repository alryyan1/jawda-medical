<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabCommentSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggestion',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
    ];

    /**
     * Get suggestions ordered by usage count
     */
    public static function getPopularSuggestions($limit = 20)
    {
        return self::orderBy('usage_count', 'desc')
                   ->orderBy('suggestion', 'asc')
                   ->limit($limit)
                   ->pluck('suggestion')
                   ->toArray();
    }

    /**
     * Add or update suggestions from text (explodes into words)
     */
    public static function addSuggestion($text)
    {
        if (empty(trim($text))) {
            return [];
        }

        $text = trim($text);
        
        // Explode text into words, filter out empty strings and very short words
        $words = array_filter(
            preg_split('/\s+/', $text),
            function($word) {
                $cleanWord = trim($word);
                return !empty($cleanWord) && strlen($cleanWord) >= 2;
            }
        );

        $addedSuggestions = [];
        
        foreach ($words as $word) {
            $cleanWord = trim($word);
            
            // Check if word already exists
            $existing = self::where('suggestion', $cleanWord)->first();
            
            if ($existing) {
                // Increment usage count for existing word
                $existing->increment('usage_count');
                $addedSuggestions[] = $existing;
            } else {
                // Create new suggestion for new word
                $newSuggestion = self::create([
                    'suggestion' => $cleanWord,
                    'usage_count' => 1
                ]);
                $addedSuggestions[] = $newSuggestion;
            }
        }
        
        return $addedSuggestions;
    }
}
