<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldTextSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FieldTextSuggestionController extends Controller
{
    /**
     * List distinct previously-typed words for a clinical form field (e.g.
     * "cardiovascular_summary"), used to power its autocomplete dropdown.
     */
    public function index(string $fieldKey): JsonResponse
    {
        return response()->json(['data' => $this->suggestionsFor($fieldKey)]);
    }

    /**
     * Register every distinct word found in the given text as a suggestion
     * for the field, so it appears in autocomplete the next time someone
     * types into that same field.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_key' => 'required|string|max:100',
            'text' => 'required|string',
        ]);

        $words = collect(preg_split('/[\s,.;:]+/u', $validated['text']) ?: [])
            ->map(fn (string $word) => trim($word))
            ->filter(fn (string $word) => $word !== '')
            ->unique();

        foreach ($words as $word) {
            FieldTextSuggestion::firstOrCreate([
                'field_key' => $validated['field_key'],
                'phrase' => $word,
            ]);
        }

        return response()->json(['data' => $this->suggestionsFor($validated['field_key'])]);
    }

    /**
     * @return Collection<int, string>
     */
    private function suggestionsFor(string $fieldKey): Collection
    {
        return FieldTextSuggestion::where('field_key', $fieldKey)
            ->orderBy('phrase')
            ->limit(500)
            ->pluck('phrase');
    }
}
