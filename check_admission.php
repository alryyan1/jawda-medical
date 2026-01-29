<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$admission = \App\Models\Admission::latest()->first();

if ($admission) {
    echo "Latest Admission ID: " . $admission->id . "\n";
    echo "Patient ID: " . $admission->patient_id . "\n";
    echo "Medical History: " . ($admission->medical_history ?? 'NULL') . "\n";
    echo "Current Medications: " . ($admission->current_medications ?? 'NULL') . "\n";
    echo "Referral Source: " . ($admission->referral_source ?? 'NULL') . "\n";
    echo "Expected Discharge Date: " . ($admission->expected_discharge_date ?? 'NULL') . "\n";
    echo "Next of Kin Name: " . ($admission->next_of_kin_name ?? 'NULL') . "\n";
    echo "Next of Kin Relation: " . ($admission->next_of_kin_relation ?? 'NULL') . "\n";
    echo "Next of Kin Phone: " . ($admission->next_of_kin_phone ?? 'NULL') . "\n";
} else {
    echo "No admissions found.\n";
}
