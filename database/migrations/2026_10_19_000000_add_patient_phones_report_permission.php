<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const PERMISSION_NAME = 'عرض تقرير أرقام هواتف المرضى';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::findOrCreate(self::PERMISSION_NAME, 'web');

        $reportRoles = Role::whereHas('permissions', fn ($q) => $q->where('name', 'عرض التقارير'))->get();
        foreach ($reportRoles as $role) {
            $role->givePermissionTo(self::PERMISSION_NAME);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', self::PERMISSION_NAME)->where('guard_name', 'web')->delete();
    }
};
