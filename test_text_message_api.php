<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Setting;

// Get settings
$settings = Setting::first();

if (!$settings || !$settings->ultramsg_token || !$settings->ultramsg_instance_id) {
    echo "Error: Ultramsg token or instance_id not configured in settings.\n";
    echo "Please configure them in the database first.\n";
    exit(1);
}

$token = $settings->ultramsg_token;
$instanceId = $settings->ultramsg_instance_id;

echo "Using Token: " . substr($token, 0, 10) . "...\n";
echo "Using Instance ID: $instanceId\n\n";

// Test data
$data = [
    'token' => $token,
    'instance_id' => $instanceId,
    'phone' => '249991961111',
    'body' => 'hi'
];

// Production URL
$url = 'https://intaj-starstechnology.com/jawda-medical/public/api/ultramsg/send-text-message-with-credentials';

echo "Testing API endpoint: $url\n";
echo "Phone number: 249991961111\n";
echo "Message: " . $data['body'] . "\n\n";

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For testing only

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Display results
echo "========================================\n";
echo "HTTP Status Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response:\n";
$responseData = json_decode($response, true);
if ($responseData) {
    echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo $response . "\n";
}
echo "========================================\n";

if ($httpCode === 200 && isset($responseData['success']) && $responseData['success'] === true) {
    echo "\n✅ SUCCESS! Message sent successfully.\n";
    echo "Message ID: " . ($responseData['message_id'] ?? 'N/A') . "\n";
} else {
    echo "\n❌ FAILED! Check the error message above.\n";
}

