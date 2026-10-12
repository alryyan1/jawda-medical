<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose doctorvisit_id column is queried heavily (e.g. the
     * CBC/chemistry/hormone presence checks on the lab queue) but had no
     * index, causing a full table scan on every lookup.
     *
     * @var array<int, string>
     */
    private const TABLES = ['sysmex', 'mindray2', 'hormone'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'doctorvisit_id') && ! $this->hasIndex($table, $table.'_doctorvisit_id_index')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->index('doctorvisit_id', $table.'_doctorvisit_id_index');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if ($this->hasIndex($table, $table.'_doctorvisit_id_index')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropIndex($table.'_doctorvisit_id_index');
                });
            }
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return count($indexes) > 0;
    }
};
