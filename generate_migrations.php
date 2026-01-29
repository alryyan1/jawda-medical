<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Load table dependencies
$tableData = json_decode(file_get_contents('table_dependencies.json'), true);

// Topological sort function
function topologicalSort($tableData)
{
    $sorted = [];
    $visited = [];
    $visiting = [];

    function visit($table, &$sorted, &$visited, &$visiting, $tableData)
    {
        if (isset($visited[$table])) {
            return;
        }

        if (isset($visiting[$table])) {
            // Circular dependency detected, but we'll handle it by allowing the table
            return;
        }

        $visiting[$table] = true;

        // Visit dependencies first
        if (isset($tableData[$table]['dependencies'])) {
            $dependencies = $tableData[$table]['dependencies'];
            if (is_array($dependencies)) {
                foreach ($dependencies as $dep) {
                    if (isset($tableData[$dep])) {
                        visit($dep, $sorted, $visited, $visiting, $tableData);
                    }
                }
            }
        }

        unset($visiting[$table]);
        $visited[$table] = true;
        $sorted[] = $table;
    }

    foreach (array_keys($tableData) as $table) {
        visit($table, $sorted, $visited, $visiting, $tableData);
    }

    return $sorted;
}

// Map MySQL types to Laravel Schema Builder methods
function mapColumnType($column)
{
    $type = strtolower($column->Type);

    // Handle types with parameters
    if (preg_match('/^(\w+)\(([^)]+)\)/', $type, $matches)) {
        $baseType = $matches[1];
        $params = $matches[2];

        switch ($baseType) {
            case 'varchar':
                return "string('{$column->Field}', {$params})";
            case 'char':
                return "char('{$column->Field}', {$params})";
            case 'decimal':
                $parts = explode(',', $params);
                return "decimal('{$column->Field}', " . trim($parts[0]) . ", " . (isset($parts[1]) ? trim($parts[1]) : '0') . ")";
            case 'enum':
                $values = str_replace("'", '"', $params);
                return "enum('{$column->Field}', [{$values}])";
            case 'bigint':
                if (strpos($type, 'unsigned') !== false) {
                    return "unsignedBigInteger('{$column->Field}')";
                }
                return "bigInteger('{$column->Field}')";
            case 'int':
            case 'integer':
                if (strpos($type, 'unsigned') !== false) {
                    return "unsignedInteger('{$column->Field}')";
                }
                return "integer('{$column->Field}')";
            case 'tinyint':
                if ($params == '1') {
                    return "boolean('{$column->Field}')";
                }
                return "tinyInteger('{$column->Field}')";
            case 'smallint':
                return "smallInteger('{$column->Field}')";
            case 'mediumint':
                return "mediumInteger('{$column->Field}')";
            default:
                return "string('{$column->Field}')";
        }
    }

    // Handle types without parameters
    switch ($type) {
        case 'text':
            return "text('{$column->Field}')";
        case 'mediumtext':
            return "mediumText('{$column->Field}')";
        case 'longtext':
            return "longText('{$column->Field}')";
        case 'json':
            return "json('{$column->Field}')";
        case 'date':
            return "date('{$column->Field}')";
        case 'datetime':
            return "dateTime('{$column->Field}')";
        case 'timestamp':
            return "timestamp('{$column->Field}')";
        case 'time':
            return "time('{$column->Field}')";
        case 'year':
            return "year('{$column->Field}')";
        case 'double':
            return "double('{$column->Field}')";
        case 'float':
            return "float('{$column->Field}')";
        case 'binary':
            return "binary('{$column->Field}')";
        case 'blob':
            return "binary('{$column->Field}')";
        default:
            if (strpos($type, 'bigint') !== false) {
                if (strpos($type, 'unsigned') !== false) {
                    return "unsignedBigInteger('{$column->Field}')";
                }
                return "bigInteger('{$column->Field}')";
            }
            if (strpos($type, 'int') !== false) {
                if (strpos($type, 'unsigned') !== false) {
                    return "unsignedInteger('{$column->Field}')";
                }
                return "integer('{$column->Field}')";
            }
            return "string('{$column->Field}')";
    }
}

// Generate migration content for a table
function generateMigration($tableName, $foreignKeys)
{
    // Get table structure
    $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");

    // Get indexes
    $indexes = DB::select("SHOW INDEX FROM `{$tableName}`");

    $migrationContent = "<?php\n\n";
    $migrationContent .= "use Illuminate\\Database\\Migrations\\Migration;\n";
    $migrationContent .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
    $migrationContent .= "use Illuminate\\Support\\Facades\\Schema;\n\n";

    $className = 'Create' . str_replace('_', '', ucwords($tableName, '_')) . 'Table';

    $migrationContent .= "return new class extends Migration\n";
    $migrationContent .= "{\n";
    $migrationContent .= "    /**\n";
    $migrationContent .= "     * Run the migrations.\n";
    $migrationContent .= "     */\n";
    $migrationContent .= "    public function up(): void\n";
    $migrationContent .= "    {\n";
    $migrationContent .= "        Schema::create('{$tableName}', function (Blueprint \$table) {\n";

    // Add columns
    foreach ($columns as $column) {
        $columnDef = "            \$table->" . mapColumnType($column);

        // Handle auto increment
        if ($column->Extra === 'auto_increment') {
            if (strpos(strtolower($column->Type), 'bigint') !== false) {
                $columnDef = "            \$table->id('{$column->Field}')";
            } else {
                $columnDef .= "->autoIncrement()";
            }
        }

        // Handle nullable
        if ($column->Null === 'YES' && $column->Field !== 'id') {
            $columnDef .= "->nullable()";
        }

        // Handle default value
        if ($column->Default !== null && $column->Default !== 'NULL') {
            if ($column->Default === 'current_timestamp()' || $column->Default === 'CURRENT_TIMESTAMP') {
                $columnDef .= "->useCurrent()";
            } else {
                $defaultValue = is_numeric($column->Default) ? $column->Default : "'{$column->Default}'";
                $columnDef .= "->default({$defaultValue})";
            }
        }

        $columnDef .= ";\n";
        $migrationContent .= $columnDef;
    }

    // Add unique indexes
    $processedIndexes = [];
    foreach ($indexes as $index) {
        if ($index->Key_name === 'PRIMARY' || isset($processedIndexes[$index->Key_name])) {
            continue;
        }

        $processedIndexes[$index->Key_name] = true;

        // Get all columns for this index
        $indexColumns = array_filter($indexes, function ($idx) use ($index) {
            return $idx->Key_name === $index->Key_name;
        });

        $columnNames = array_map(function ($idx) {
            return "'{$idx->Column_name}'";
        }, $indexColumns);

        if ($index->Non_unique == 0) {
            $migrationContent .= "            \$table->unique([" . implode(', ', $columnNames) . "], '{$index->Key_name}');\n";
        } elseif ($index->Key_name !== 'PRIMARY') {
            // Regular index - we'll skip these for now to keep migrations simpler
            // $migrationContent .= "            \$table->index([" . implode(', ', $columnNames) . "], '{$index->Key_name}');\n";
        }
    }

    // Add foreign keys
    foreach ($foreignKeys as $fk) {
        $onDelete = 'cascade'; // Default behavior
        $migrationContent .= "            \$table->foreign('{$fk->COLUMN_NAME}', '{$fk->CONSTRAINT_NAME}')\n";
        $migrationContent .= "                  ->references('{$fk->REFERENCED_COLUMN_NAME}')\n";
        $migrationContent .= "                  ->on('{$fk->REFERENCED_TABLE_NAME}')\n";
        $migrationContent .= "                  ->onDelete('{$onDelete}');\n";
    }

    $migrationContent .= "        });\n";
    $migrationContent .= "    }\n\n";
    $migrationContent .= "    /**\n";
    $migrationContent .= "     * Reverse the migrations.\n";
    $migrationContent .= "     */\n";
    $migrationContent .= "    public function down(): void\n";
    $migrationContent .= "    {\n";
    $migrationContent .= "        Schema::dropIfExists('{$tableName}');\n";
    $migrationContent .= "    }\n";
    $migrationContent .= "};\n";

    return $migrationContent;
}

// Sort tables by dependencies
echo "Sorting tables by dependencies...\n";
$sortedTables = topologicalSort($tableData);

echo "Found " . count($sortedTables) . " tables to process.\n";
echo "Generating migrations...\n\n";

// Generate migrations with timestamps
$baseTimestamp = strtotime('2024-01-01 00:00:00');
$counter = 0;

foreach ($sortedTables as $tableName) {
    try {
        echo "Processing table: {$tableName}\n";

        // Generate timestamp for this migration
        $timestamp = date('Y_m_d_His', $baseTimestamp + ($counter * 10));
        $counter++;

        // Get foreign keys for this table
        $foreignKeys = [];
        if (isset($tableData[$tableName]['foreign_keys'])) {
            foreach ($tableData[$tableName]['foreign_keys'] as $fk) {
                $foreignKeys[] = (object)$fk;
            }
        }

        // Generate migration content
        $migrationContent = generateMigration($tableName, $foreignKeys);

        // Save migration file
        $filename = "database/migrations/system_setup/{$timestamp}_create_{$tableName}_table.php";
        file_put_contents($filename, $migrationContent);

        echo "  ✓ Generated: {$filename}\n";
    } catch (Exception $e) {
        echo "  ✗ Error processing {$tableName}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Migration generation complete! ===\n";
echo "Total migrations created: {$counter}\n";
echo "Location: database/migrations/system_setup/\n";
