<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Load table dependencies
$tableData = json_decode(file_get_contents('table_dependencies.json'), true);

// Topological sort function (reused)
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
            return;
        }
        $visiting[$table] = true;

        if (isset($tableData[$table]['dependencies'])) {
            // Ensure dependencies is array
            $deps = $tableData[$table]['dependencies'];
            if (!is_array($deps)) {
                $deps = (array)$deps;
            }
            foreach ($deps as $dep) {
                if (isset($tableData[$dep])) {
                    visit($dep, $sorted, $visited, $visiting, $tableData);
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

// Get available seeders
$seederFiles = glob('database/seeders/system_setup_seeders/*TableSeeder.php');
$availableSeeders = [];
foreach ($seederFiles as $file) {
    if (preg_match('/([A-Za-z0-9_]+)TableSeeder\.php$/', basename($file), $matches)) {
        // We need to map StudlyCase back to snake_case table name roughly
        // Or we can just check which table matches the seeder name
        // Convention was <TableName>TableSeeder.php where TableName is StudlyCase of table

        // Let's rely on the file name being generated from table name.
        // But wait, iseed converts table_name to TableNameTableSeeder.
        // So we need to reverse that logic or just check if the seeder exists for a table.

        // Simpler: Iterate all tables, convert to Seeder name, check if file exists.
        $availableSeeders[$file] = true;
    }
}

$tables = topologicalSort($tableData);
$orderedSeeders = [];

foreach ($tables as $table) {
    // strict conversion used by iseed usually:
    // Illuminate\Support\Str::studly($table) . 'TableSeeder'
    $className = \Illuminate\Support\Str::studly($table) . 'TableSeeder';
    $path = "database/seeders/system_setup_seeders/{$className}.php";

    if (file_exists($path)) {
        $orderedSeeders[] = "        \$this->call(\\Database\\Seeders\\system_setup_seeders\\{$className}::class);";
    }
}

$content = "<?php\n\n";
$content .= "namespace Database\\Seeders;\n\n";
$content .= "use Illuminate\\Database\\Seeder;\n\n";
$content .= "class SystemSetupSeeder extends Seeder\n";
$content .= "{\n";
$content .= "    /**\n";
$content .= "     * Run the database seeds.\n";
$content .= "     *\n";
$content .= "     * @return void\n";
$content .= "     */\n";
$content .= "    public function run()\n";
$content .= "    {\n";
$content .= "        // Disable foreign key checks to prevent issues with circular deps or order\n";
$content .= "        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');\n\n";
$content .= implode("\n", $orderedSeeders) . "\n\n";
$content .= "        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');\n";
$content .= "    }\n";
$content .= "}\n";

file_put_contents('database/seeders/SystemSetupSeeder.php', $content);
echo "Generated database/seeders/SystemSetupSeeder.php with " . count($orderedSeeders) . " seeders ordered by dependency.\n";
