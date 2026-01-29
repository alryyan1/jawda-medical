<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Deno;
use App\Models\Deposit;
use App\Models\Doctor;
use App\Models\DrugCategory;
use App\Models\Patient;
use App\Models\PaymentType;
use App\Models\PharmacyType;
use App\Models\Shift;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();
        //Specialist::truncate();
        //Doctor::truncate();






       $specialist =  Specialist::create(['name'=>'الباطنيه']);
//       $doctor = Doctor::factory(10)->create();
//       Patient::factory(10)->create();
        $this->call(UnitsTableSeeder::class);
        $this->call(PackageTableSeeder::class);
        $this->call(ContainersTableSeeder::class);
        $this->call(MainTestsTableSeeder::class);
        $this->call(ChildTestsTableSeeder::class);
//        $this->call(ItemsTableSeeder::class);
        $this->call(ServiceGroupsTableSeeder::class);
//        $this->call(ServicesTableSeeder::class);
//        $this->call(DepositItemsTableSeeder::class);
        $this->call(PermissionsTableSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(ChildTestOptionsTableSeeder::class);
        $this->call(CbcBindingsTableSeeder::class);
        $this->call(ChemistryBindingsTableSeeder::class);
        $this->call(RoutesTableSeeder::class);
        $this->call(RoleHasPermissionsTableSeeder::class);
        // $this->call(UserRoutesTableSeeder::class);
        $this->call(SubRoutesTableSeeder::class);
        $this->call(ChiefComplainTableSeeder::class);
        $this->call(DrugsTableSeeder::class);
        $this->call(Sysmex550TableSeeder::class);
        $this->call(DiagnosisTableSeeder::class);
//        $this->call(NewDrugsTableSeeder::class);

        // $this->call(UserSubRoutesTableSeeder::class);
        $this->call(SpecialistsTableSeeder::class);
        // $this->call(AccountHierarchyTableSeeder::class);
        $this->call(AttendanceSettingsTableSeeder::class);
        $this->call(BedsTableSeeder::class);
        $this->call(CashTallyTableSeeder::class);
        $this->call(ClientsTableSeeder::class);
        $this->call(DenosTableSeeder::class);
        $this->call(DepositsTableSeeder::class);
        $this->call(DrugCategoriesTableSeeder::class);
        $this->call(PackagesTableSeeder::class);
        $this->call(PaymentTypesTableSeeder::class);
        $this->call(PersonalAccessTokensTableSeeder::class);
        $this->call(PharmacyTypesTableSeeder::class);
        $this->call(RoomsTableSeeder::class);
        $this->call(SectionsTableSeeder::class);
        $this->call(ShiftsTableSeeder::class);
        $this->call(SuppliersTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        $this->call(WardsTableSeeder::class);
    }
}
