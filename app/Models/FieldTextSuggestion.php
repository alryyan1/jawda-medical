<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single previously-typed word, scoped to a clinical form field (e.g.
 * "cardiovascular_summary"), offered back as an autocomplete suggestion the
 * next time a doctor types into that same field.
 */
class FieldTextSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_key',
        'phrase',
    ];
}
