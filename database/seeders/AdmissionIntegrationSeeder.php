<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdmissionIntegrationSeeder extends Seeder
{
    /**
     * Ensure the system account used to attribute patients created by the
     * jawda-inpatient admission system (via the public
     * /api/public/admission-patients endpoint) exists and can register cash
     * patients. The account has no usable password; it is never logged into.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['username' => 'admission_system'],
            [
                'name' => 'نظام التنويم',
                'password' => Hash::make(Str::random(40)),
                'is_active' => true,
                'is_supervisor' => false,
            ]
        );

        if (! $user->hasPermissionTo('تسجيل مريض كاش')) {
            $user->givePermissionTo('تسجيل مريض كاش');
        }
    }
}
