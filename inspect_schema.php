<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "Checking operation_finance_items table...\n";
if (Schema::hasTable('operation_finance_items')) {
    echo "Table exists.\n";
    print_r(Schema::getColumnListing('operation_finance_items'));
} else {
    echo "Table does NOT exist.\n";
}

echo "Checking operation_items table...\n";
if (Schema::hasTable('operation_items')) {
    echo "Table exists.\n";
} else {
    echo "Table does NOT exist.\n";
}
