<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename inventory_notification_number to cloud_api_token
        // First, check if the column exists and get its definition
        $columns = DB::select("SHOW COLUMNS FROM settings WHERE Field = 'inventory_notification_number'");
        if (!empty($columns)) {
            $column = $columns[0];
            $type = $column->Type;
            $null = $column->Null === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $column->Default !== null ? "DEFAULT '{$column->Default}'" : '';
            
            DB::statement("ALTER TABLE settings CHANGE COLUMN `inventory_notification_number` `cloud_api_token` {$type} {$null} {$default}");
        }
        
        // Rename currency to phone_number_id
        $columns = DB::select("SHOW COLUMNS FROM settings WHERE Field = 'currency'");
        if (!empty($columns)) {
            $column = $columns[0];
            $type = $column->Type;
            $null = $column->Null === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $column->Default !== null ? "DEFAULT '{$column->Default}'" : '';
            
            DB::statement("ALTER TABLE settings CHANGE COLUMN `currency` `phone_number_id` {$type} {$null} {$default}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert cloud_api_token back to inventory_notification_number
        $columns = DB::select("SHOW COLUMNS FROM settings WHERE Field = 'cloud_api_token'");
        if (!empty($columns)) {
            $column = $columns[0];
            $type = $column->Type;
            $null = $column->Null === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $column->Default !== null ? "DEFAULT '{$column->Default}'" : '';
            
            DB::statement("ALTER TABLE settings CHANGE COLUMN `cloud_api_token` `inventory_notification_number` {$type} {$null} {$default}");
        }
        
        // Revert phone_number_id back to currency
        $columns = DB::select("SHOW COLUMNS FROM settings WHERE Field = 'phone_number_id'");
        if (!empty($columns)) {
            $column = $columns[0];
            $type = $column->Type;
            $null = $column->Null === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $column->Default !== null ? "DEFAULT '{$column->Default}'" : '';
            
            DB::statement("ALTER TABLE settings CHANGE COLUMN `phone_number_id` `currency` {$type} {$null} {$default}");
        }
    }
};
