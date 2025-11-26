<?php
/**
 * Helper script to convert a PDF file to base64
 * Usage: php get_base64_pdf.php path/to/file.pdf
 */

if ($argc < 2) {
    echo "Usage: php get_base64_pdf.php <path-to-pdf-file>\n";
    echo "Example: php get_base64_pdf.php C:\\Users\\User\\Documents\\test.pdf\n";
    exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
    echo "Error: File not found: $filePath\n";
    exit(1);
}

if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'pdf') {
    echo "Warning: File extension is not .pdf\n";
}

$fileContent = file_get_contents($filePath);
if ($fileContent === false) {
    echo "Error: Could not read file: $filePath\n";
    exit(1);
}

$base64 = base64_encode($fileContent);

echo "\n========================================\n";
echo "Base64 Encoded PDF:\n";
echo "========================================\n";
echo $base64 . "\n";
echo "========================================\n";
echo "File size: " . strlen($fileContent) . " bytes\n";
echo "Base64 size: " . strlen($base64) . " characters\n";
echo "========================================\n\n";

// Also save to a text file for easy copying
$outputFile = pathinfo($filePath, PATHINFO_FILENAME) . '_base64.txt';
file_put_contents($outputFile, $base64);
echo "Base64 string also saved to: $outputFile\n";



