<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

echo "=== VERIFYING ADMIN SETUP ===\n\n";

// 1. Verify Permissions
$permCount = Permission::count();
echo "Permissions count: {$permCount}\n";
if ($permCount > 10) echo "✓ Permissions created\n";
else echo "✗ Permissions missing\n";

// 2. Verify Role
$role = Role::where('name', 'admin')->first();
if ($role) {
    echo "✓ Admin role exists\n";
    echo "  Permissions assigned: " . $role->permissions()->count() . "\n";
} else {
    echo "✗ Admin role missing\n";
}

// 3. Verify User
$user = User::where('username', 'admin')->first();
if ($user) {
    echo "✓ Admin user exists\n";
    echo "  Name: {$user->name}\n";
    echo "  Nav Items Length: " . strlen($user->nav_items) . "\n";

    // Check Role Assignment
    if ($user->hasRole('admin')) {
        echo "✓ Admin role assigned to user\n";
    } else {
        echo "✗ Admin role NOT assigned to user\n";
    }
} else {
    echo "✗ Admin user missing\n";
}

// 4. Verify Auto Increment
echo "\n=== VERIFYING AUTO INCREMENT ===\n";

function getAutoIncrement($table)
{
    $result = DB::select("SHOW TABLE STATUS LIKE '{$table}'");
    return $result[0]->Auto_increment;
}

$patientsAI = getAutoIncrement('patients');
$doctorVisitsAI = getAutoIncrement('doctor_visits');

echo "Patients Auto_increment: {$patientsAI}\n";
echo "Doctor Visits Auto_increment: {$doctorVisitsAI}\n";

if ($patientsAI >= 1000) echo "✓ Patients AI correct\n";
else echo "✗ Patients AI incorrect (< 1000)\n";
if ($doctorVisitsAI >= 1000) echo "✓ Doctor Visits AI correct\n";
else echo "✗ Doctor Visits AI incorrect (< 1000)\n";
