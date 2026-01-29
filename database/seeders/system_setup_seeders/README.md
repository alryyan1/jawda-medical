# System Setup Seeders

This directory contains automatically generated seeder files for all tables with data in the `alroomy_jaw da` database.

**Generated on:** 2026-01-29  
**Total seeders:** 48  
**Generated using:** orangehill/iseed package

## Purpose

These seeders are designed for:

- System initialization on fresh installations
- Replicating the current database configuration in development/testing environments
- Restoring system configuration data

## Contents

This directory contains seeders for:

- **System configuration** (wards, rooms, beds, permissions, roles)
- **Medical reference data** (main_tests, child_tests, units, containers)
- **Pharmacy data** (pharmacy_types, drug_categories)
- **Financial setup** (finance_accounts, account_categories)
- **And more...**

## Usage

### Run Individual Seeder

```bash
php artisan db:seed --class=Database\\Seeders\\system_setup_seeders\\WardsTableSeeder
```

### Run All Seeders

Create a master seeder file `SystemSetupSeeder.php` that calls all seeders in the correct order.

## ⚠️ Important Notes

- **Warning:** These seeders use `DB::table()->delete()` which will truncate tables before inserting
- Use only on fresh databases or for system initialization
- Do not run on production databases without backing up first
- Some seeders may need to run in specific order due to foreign key constraints

## Seeder Details

All seeders follow this pattern:

1. Delete existing data from the table
2. Insert all rows from the source database
3. Preserve all column values including timestamps and IDs

Total data: ~2,500+ rows across all 48 tables
