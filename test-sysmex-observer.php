<?php

/**
 * Test script to verify SysmexResultObserver is working
 * This script creates a test SysmexResult record to trigger the observer
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\SysmexResult;
use App\Models\Doctorvisit;
use App\Models\Patient;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing SysmexResultObserver...\n";

try {
    // Find a doctor visit to use for testing
    $doctorVisit = Doctorvisit::with('patient')->find(29429);
    
    if (!$doctorVisit) {
        echo "No doctor visits found. Please create a doctor visit first.\n";
        exit(1);
    }
    
    echo "Using doctor visit ID: {$doctorVisit->id}\n";
    echo "Patient: {$doctorVisit->patient->name}\n";
    
    // Create a test SysmexResult record
    $sysmexData = [
        'doctorvisit_id' => $doctorVisit->id,
        'wbc' => 7.5,
        'rbc' => 4.2,
        'hgb' => 12.5,
        'hct' => 38.0,
        'mcv' => 90.5,
        'mch' => 29.8,
        'mchc' => 32.9,
        'plt' => 250,
        'lym_p' => 35.0,
        'mxd_p' => 8.0,
        'neut_p' => 57.0,
        'lym_c' => 2.6,
        'mxd_c' => 0.6,
        'neut_c' => 4.3,
        'rdw_sd' => 42.0,
        'rdw_cv' => 13.5,
        'pdw' => 12.0,
        'mpv' => 9.5,
        'plcr' => 0.25,
    ];
    
    echo "Creating SysmexResult record...\n";
    
    // This should trigger the SysmexResultObserver::created method
    $sysmexResult = SysmexResult::create($sysmexData);
    
    echo "SysmexResult created successfully!\n";
    echo "ID: {$sysmexResult->id}\n";
    echo "Doctor Visit ID: {$sysmexResult->doctorvisit_id}\n";
    echo "WBC: {$sysmexResult->wbc}\n";
    echo "RBC: {$sysmexResult->rbc}\n";
    echo "HGB: {$sysmexResult->hgb}\n";
    
    echo "\nCheck the logs and realtime server to see if the event was triggered.\n";
    echo "The observer should have sent a realtime event to notify the frontend.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
