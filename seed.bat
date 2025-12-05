@echo off
cd /d "%~dp0"

echo Running all seeders from database\setup-seeders\seeders...
echo.

php artisan db:seed --class=Database\Seeders\AccountCategoriesTableSeeder
php artisan db:seed --class=Database\Seeders\AccountHierarchyTableSeeder
php artisan db:seed --class=Database\Seeders\CbcBindingsTableSeeder
php artisan db:seed --class=Database\Seeders\ChemistryBindingsTableSeeder
php artisan db:seed --class=Database\Seeders\ChiefComplainTableSeeder
php artisan db:seed --class=Database\Seeders\ChildTestOptionsTableSeeder
php artisan db:seed --class=Database\Seeders\ChildTestsTableSeeder
php artisan db:seed --class=Database\Seeders\ClientSeeder
php artisan db:seed --class=Database\Seeders\ContainersTableSeeder
php artisan db:seed --class=Database\Seeders\CreditEntriesTableSeeder
php artisan db:seed --class=Database\Seeders\DebitEntriesTableSeeder
php artisan db:seed --class=Database\Seeders\DepositItemsTableSeeder
php artisan db:seed --class=Database\Seeders\DiagnosisTableSeeder
php artisan db:seed --class=Database\Seeders\DrugCategoriesTableSeeder
php artisan db:seed --class=Database\Seeders\DrugsTableSeeder
php artisan db:seed --class=Database\Seeders\FinanceAccountsTableSeeder
php artisan db:seed --class=Database\Seeders\FinanceEntriesTableSeeder
php artisan db:seed --class=Database\Seeders\ItemsTableSeeder
php artisan db:seed --class=Database\Seeders\MainTestsTableSeeder
php artisan db:seed --class=Database\Seeders\NewDrugsTableSeeder
php artisan db:seed --class=Database\Seeders\PackageTableSeeder
php artisan db:seed --class=Database\Seeders\PermissionsTableSeeder
php artisan db:seed --class=Database\Seeders\PharmacyTypesTableSeeder
php artisan db:seed --class=Database\Seeders\RoleHasPermissionsTableSeeder
php artisan db:seed --class=Database\Seeders\RolesTableSeeder
php artisan db:seed --class=Database\Seeders\RoutesTableSeeder
php artisan db:seed --class=Database\Seeders\SectionSeeder
php artisan db:seed --class=Database\Seeders\ServiceGroupsTableSeeder
php artisan db:seed --class=Database\Seeders\ServicesTableSeeder
php artisan db:seed --class=Database\Seeders\SettingsTableSeeder
php artisan db:seed --class=Database\Seeders\SpecialistsTableSeeder
php artisan db:seed --class=Database\Seeders\SubRoutesTableSeeder
php artisan db:seed --class=Database\Seeders\SupplierSeeder
php artisan db:seed --class=Database\Seeders\Sysmex550TableSeeder
php artisan db:seed --class=Database\Seeders\UnitsTableSeeder
php artisan db:seed --class=Database\Seeders\UserRoutesTableSeeder
php artisan db:seed --class=Database\Seeders\UserSubRoutesTableSeeder

echo.
echo All seeders completed!
pause
