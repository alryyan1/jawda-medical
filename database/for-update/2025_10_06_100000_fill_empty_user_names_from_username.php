<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Set users.name = users.username where name is NULL or empty, and username is not NULL/empty
        DB::statement("UPDATE `users` SET `name` = `username` WHERE (`name` IS NULL OR `name` = '') AND `username` IS NOT NULL AND `username` <> ''");
    }

    public function down(): void
    {
        // Irreversible data change: intentionally left blank.
    }
};


