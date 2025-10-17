<?php

/**
 * Test script for "بنكك اليوم" functionality
 * This script can be run to test the bankak today feature
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\BankakImage;
use App\Services\GeminiService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing 'بنكك اليوم' functionality...\n\n";

try {
    // Test 1: Check if there are any images for today
    $today = now()->format('Y-m-d');
    $todayImages = BankakImage::whereDate('created_at', $today)->get();
    
    echo "Date: {$today}\n";
    echo "Images found for today: " . $todayImages->count() . "\n\n";
    
    if ($todayImages->isEmpty()) {
        echo "No images found for today. Creating a test image record...\n";
        
        // Create a test image record (you can replace with actual image data)
        $testImage = BankakImage::create([
            'image_url' => 'test/test_image.jpg',
            'doctorvisit_id' => null,
            'phone' => '1234567890',
        ]);
        
        echo "Test image created with ID: " . $testImage->id . "\n";
        $todayImages = collect([$testImage]);
    }
    
    // Test 2: Test Gemini service
    echo "\nTesting Gemini service...\n";
    $geminiService = new GeminiService();
    
    // Test with a sample image URL (replace with actual image URL)
    $testImageUrl = 'https://via.placeholder.com/300x200/000000/FFFFFF?text=Test+Image';
    
    echo "Testing image analysis with URL: {$testImageUrl}\n";
    $result = $geminiService->analyzeImage($testImageUrl, 'استخرج المبلغ فقط');
    
    if ($result['success']) {
        echo "Gemini analysis successful!\n";
        echo "Analysis result: " . $result['data']['analysis'] . "\n";
    } else {
        echo "Gemini analysis failed: " . $result['error'] . "\n";
    }
    
    // Test 3: Test amount extraction
    echo "\nTesting amount extraction...\n";
    $testAnalyses = [
        'المبلغ هو 150.50 ريال',
        'Amount: 250',
        'Total: 1,200.75',
        'المبلغ يساوي 500',
        'No amount found'
    ];
    
    foreach ($testAnalyses as $analysis) {
        $amount = extractAmountFromAnalysis($analysis);
        echo "Analysis: '{$analysis}' -> Amount: {$amount}\n";
    }
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

/**
 * Extract numeric amount from Gemini analysis text (copied from WebHookController)
 */
function extractAmountFromAnalysis(string $analysis): float
{
    // Remove common Arabic text and keep only numbers
    $cleaned = preg_replace('/[^\d.,]/u', '', $analysis);
    
    // Handle different decimal separators
    $cleaned = str_replace(',', '.', $cleaned);
    
    // Extract the first valid number
    if (preg_match('/(\d+\.?\d*)/', $cleaned, $matches)) {
        return (float) $matches[1];
    }
    
    return 0;
}
