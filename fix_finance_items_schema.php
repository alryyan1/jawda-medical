<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Fixing operation_finance_items table schema manually...\n";

if (!Schema::hasTable('operation_finance_items')) {
    echo "Error: operation_finance_items table does not exist.\n";
    exit(1);
}

Schema::table('operation_finance_items', function (Blueprint $table) {
    if (!Schema::hasColumn('operation_finance_items', 'operation_item_id')) {
        echo "Adding operation_item_id column...\n";
        $table->unsignedBigInteger('operation_item_id')->nullable()->after('operation_id');
        $table->foreign('operation_item_id')->references('id')->on('operation_items')->onDelete('set null');
    } else {
        echo "operation_item_id column already exists.\n";
    }
});

Schema::table('operation_finance_items', function (Blueprint $table) {
    if (Schema::hasColumn('operation_finance_items', 'item_type')) {
        echo "Dropping item_type column...\n";
        $table->dropColumn('item_type');
    }
    if (Schema::hasColumn('operation_finance_items', 'category')) {
        echo "Dropping category column...\n";
        $table->dropColumn('category');
    }
});

echo "Schema update completed successfully.\n";
