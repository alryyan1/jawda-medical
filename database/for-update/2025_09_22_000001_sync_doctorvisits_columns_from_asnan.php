<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Source schema containing the desired structure
        $sourceSchema = 'asnan';

        // Ensure target table exists
        if (!Schema::hasTable('doctorvisits')) {
            return;
        }

        // Get columns from source schema (asnan)
        $sourceColumns = DB::table('information_schema.columns')
            ->select('COLUMN_NAME', 'DATA_TYPE', 'COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT', 'EXTRA')
            ->where('TABLE_SCHEMA', $sourceSchema)
            ->where('TABLE_NAME', 'doctorvisits')
            ->orderBy('ORDINAL_POSITION')
            ->get();

        if ($sourceColumns->isEmpty()) {
            return;
        }

        foreach ($sourceColumns as $column) {
            $name = $column->COLUMN_NAME;

            // Skip if already exists in target
            if (Schema::hasColumn('doctorvisits', $name)) {
                continue;
            }

            // Do not attempt to recreate primary key/id columns here
            if ($name === 'id') {
                continue;
            }

            // These columns are handled by a dedicated migration to ensure data backfill and constraints
            if (in_array($name, [
                'visit_date',
                'visit_time',
                'status',
                'visit_type',
                'queue_number',
                'reason_for_visit',
                'visit_notes',
            ], true)) {
                continue;
            }

            Schema::table('doctorvisits', function (Blueprint $table) use ($column) {
                $this->addColumnFromInformationSchema($table, $column);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $sourceSchema = 'asnan';

        if (!Schema::hasTable('doctorvisits')) {
            return;
        }

        $sourceColumns = DB::table('information_schema.columns')
            ->select('COLUMN_NAME')
            ->where('TABLE_SCHEMA', $sourceSchema)
            ->where('TABLE_NAME', 'doctorvisits')
            ->get();

        if ($sourceColumns->isEmpty()) {
            return;
        }

        // Drop only columns that match the source but were added by this migration (best-effort)
        foreach ($sourceColumns as $column) {
            $name = $column->COLUMN_NAME;

            if ($name === 'id') {
                continue;
            }

            if (Schema::hasColumn('doctorvisits', $name)) {
                Schema::table('doctorvisits', function (Blueprint $table) use ($name) {
                    // Guard against FKs pointing to this column
                    try { $table->dropForeign([$name]); } catch (\Throwable $e) {}
                });

                Schema::table('doctorvisits', function (Blueprint $table) use ($name) {
                    try { $table->dropColumn($name); } catch (\Throwable $e) {}
                });
            }
        }
    }

    /**
     * Add a column to the table based on MySQL information_schema metadata.
     */
    private function addColumnFromInformationSchema(Blueprint $table, object $column): void
    {
        $name = $column->COLUMN_NAME;
        $dataType = strtolower($column->DATA_TYPE);
        $columnType = strtolower($column->COLUMN_TYPE);
        $isNullable = strtoupper($column->IS_NULLABLE) === 'YES';
        $default = $column->COLUMN_DEFAULT;
        $extra = strtolower((string) $column->EXTRA);

        $definition = null;

        // Helper to mark nullable/defaults consistently
        $applyNullAndDefault = function ($col) use ($isNullable, $default) {
            if ($isNullable) {
                $col->nullable();
            }

            // Apply simple scalar defaults only (avoid CURRENT_TIMESTAMP and expressions)
            if (!is_null($default) && !is_string($default) || (is_string($default) && stripos($default, 'current_') === false && strpos($default, '(') === false)) {
                $col->default($default);
            }

            return $col;
        };

        // Map common MySQL types to Laravel Blueprint
        switch ($dataType) {
            case 'bigint':
                $definition = str_contains($columnType, 'unsigned') ? $table->unsignedBigInteger($name) : $table->bigInteger($name);
                break;
            case 'int':
            case 'integer':
                $definition = str_contains($columnType, 'unsigned') ? $table->unsignedInteger($name) : $table->integer($name);
                break;
            case 'smallint':
                $definition = str_contains($columnType, 'unsigned') ? $table->unsignedSmallInteger($name) : $table->smallInteger($name);
                break;
            case 'mediumint':
                $definition = $table->mediumInteger($name);
                break;
            case 'tinyint':
                if (preg_match('/tinyint\(1\)/', $columnType)) {
                    $definition = $table->boolean($name);
                } else {
                    $definition = str_contains($columnType, 'unsigned') ? $table->unsignedTinyInteger($name) : $table->tinyInteger($name);
                }
                break;
            case 'varchar':
                $length = 255;
                if (preg_match('/varchar\((\d+)\)/', $columnType, $m)) {
                    $length = (int) $m[1];
                }
                $definition = $table->string($name, $length);
                break;
            case 'char':
                $length = 255;
                if (preg_match('/char\((\d+)\)/', $columnType, $m)) {
                    $length = (int) $m[1];
                }
                $definition = $table->char($name, $length);
                break;
            case 'text':
                $definition = $table->text($name);
                break;
            case 'mediumtext':
                $definition = $table->mediumText($name);
                break;
            case 'longtext':
                $definition = $table->longText($name);
                break;
            case 'json':
                $definition = $table->json($name);
                break;
            case 'datetime':
                $definition = $table->dateTime($name);
                break;
            case 'timestamp':
                $definition = $table->timestamp($name);
                break;
            case 'date':
                $definition = $table->date($name);
                break;
            case 'time':
                $definition = $table->time($name);
                break;
            case 'year':
                $definition = $table->year($name);
                break;
            case 'decimal':
            case 'numeric':
                $precision = 10; $scale = 0;
                if (preg_match('/decimal\((\d+),(\d+)\)/', $columnType, $m)) {
                    $precision = (int) $m[1];
                    $scale = (int) $m[2];
                }
                $definition = $table->decimal($name, $precision, $scale);
                break;
            case 'double':
                $definition = $table->double($name);
                break;
            case 'float':
                $definition = $table->float($name);
                break;
            case 'enum':
                // Fallback to string as replicating enum values portably is complex here
                $definition = $table->string($name, 191);
                break;
            case 'uuid':
                $definition = $table->uuid($name);
                break;
            case 'binary':
            case 'varbinary':
                $definition = $table->binary($name);
                break;
            default:
                // Default fallback type
                $definition = $table->string($name, 191);
                break;
        }

        if ($definition) {
            $applyNullAndDefault($definition);

            // Auto-increment is not applied to non-primary new columns here
            // Add unsigned for decimals if specified
            if (in_array($dataType, ['decimal', 'numeric']) && str_contains($columnType, 'unsigned')) {
                // No direct unsigned for decimal in Laravel; skip
            }
        }
    }
};


