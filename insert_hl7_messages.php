<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

/**
 * Insert HL7 message into database
 */
function insertHL7Message($rawMessage, $device = null) {
    try {
        // Parse the message to extract metadata
        $lines = explode("\n", trim($rawMessage));
        $mshLine = $lines[0];
        $fields = explode('|', $mshLine);
        
        // Extract message type from field 8
        $messageType = isset($fields[8]) ? $fields[8] : null;
        
        // Extract sending application from field 2
        $sendingApp = isset($fields[2]) ? $fields[2] : null;
        
        // Extract sending facility from field 3
        $sendingFacility = isset($fields[3]) ? $fields[3] : null;
        
        // Extract patient ID from PID segment if present
        $patientId = null;
        foreach ($lines as $line) {
            if (strpos($line, 'PID|') === 0) {
                $pidFields = explode('|', $line);
                $patientId = isset($pidFields[3]) ? $pidFields[3] : null;
                break;
            }
        }
        
        // Use device parameter or extract from sending facility
        $deviceName = $device ?: $sendingFacility;
        
        // Insert into database
        $result = DB::table('hl7_messages')->insert([
            'raw_message' => $rawMessage,
            'device' => $deviceName,
            'message_type' => $messageType,
            'patient_id' => $patientId,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        if ($result) {
            echo "✅ Successfully inserted HL7 message!\n";
            echo "📋 Message Details:\n";
            echo "   Device: {$deviceName}\n";
            echo "   Message Type: {$messageType}\n";
            echo "   Sending Application: {$sendingApp}\n";
            echo "   Sending Facility: {$sendingFacility}\n";
            echo "   Patient ID: " . ($patientId ?: 'N/A') . "\n";
            echo "   Timestamp: " . now()->format('Y-m-d H:i:s') . "\n\n";
            return true;
        } else {
            echo "❌ Failed to insert message into database.\n\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "❌ Error inserting message: " . $e->getMessage() . "\n\n";
        return false;
    }
}

// Example usage - you can add more messages here
$messages = [
    [
        'message' => "MSH|^~\\&|Mindray|BS-200|||20250823154652||ACK^Q03|4|P|2.3.1||||||ASCII|||\nMSA|AA|4|Message accepted|||0|\nERR|0|",
        'device' => 'BS-200'
    ],
    // Add more messages here as needed
];

echo "🚀 Starting HL7 message insertion...\n\n";

$successCount = 0;
$totalCount = count($messages);

foreach ($messages as $index => $messageData) {
    echo "📝 Processing message " . ($index + 1) . " of {$totalCount}...\n";
    if (insertHL7Message($messageData['message'], $messageData['device'])) {
        $successCount++;
    }
}

echo "📊 Summary:\n";
echo "   Total messages: {$totalCount}\n";
echo "   Successfully inserted: {$successCount}\n";
echo "   Failed: " . ($totalCount - $successCount) . "\n";

if ($successCount > 0) {
    echo "\n🎉 You can now view these messages in the HL7 Parser page!\n";
    echo "   Go to: /hl7-parser and click 'Load from Database'\n";
}
