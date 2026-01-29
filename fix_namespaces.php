<?php

$dir = __DIR__ . '/database/seeders/system_setup_seeders';
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'README.md') continue;

    $content = file_get_contents($file);

    // Replace the namespace
    $newContent = str_replace(
        'namespace Database\Seeders;',
        'namespace Database\Seeders\system_setup_seeders;',
        $content
    );

    file_put_contents($file, $newContent);
    echo "Updated namespace in " . basename($file) . "\n";
}

echo "All files updated.\n";
