# Semen Analysis Test Setup

This document explains how to recreate the Semen Analysis test structure using the provided migration and seeder files.

## Files Created

1. **Migration**: `database/migrations/2025_10_09_012920_create_semen_analysis_test_structure.php`
2. **Seeder**: `database/seeders/SemenAnalysisTestSeeder.php`

## How to Use

### Option 1: Using Migration (Recommended for fresh installations)

```bash
# Run the migration to create the semen analysis test structure
php artisan migrate

# To rollback (remove the test structure)
php artisan migrate:rollback --step=1
```

### Option 2: Using Seeder (Recommended for existing installations)

```bash
# Run the seeder to create the semen analysis test
php artisan db:seed --class=SemenAnalysisTestSeeder

# Or add it to your DatabaseSeeder and run all seeders
php artisan db:seed
```

### Option 3: Manual Recreation

If you need to recreate the test manually, you can use the seeder as a reference for the exact structure.

## Test Structure

### Main Test
- **Name**: `semen_analysis`
- **Special Test**: `true`
- **Available**: `true`
- **Divided**: `true`

### Child Groups (4 groups)

1. **PERSONAL INFORMATION** (7 tests)
2. **PHYSICO – CHEMICAL PROPERTIES** (7 tests)
3. **MORPHOLOGY** (12 tests)
4. **STATISTICS** (9 tests)

**Total**: 35 child tests

## Notes

- The migration and seeder include safety checks to prevent duplicate creation
- The seeder will skip creation if the test already exists
- The migration's `down()` method will cleanly remove the test structure
- Both files use the first available container for the main test

## Verification

After running either the migration or seeder, you can verify the creation by:

1. Checking the main_tests table for a record with `main_test_name = 'semen_analysis'`
2. Checking the child_groups table for the 4 groups
3. Checking the child_tests table for 35 tests linked to the main test

## Database Requirements

- At least one container must exist in the `containers` table
- The `main_tests`, `child_groups`, and `child_tests` tables must exist
- The `is_special_test` column must exist in the `main_tests` table
