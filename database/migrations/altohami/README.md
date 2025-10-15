# Altohami Database Migration Files

This folder contains all migration files related to copying data from the `altohami` database to the current `alroomy_jawda` database.

## Migration Files Overview

### 1. Core Data Migrations
- **`2025_10_15_005302_copy_main_tests_from_altohami.php`** - Copies main tests data from altohami.main_tests
- **`2025_10_15_005309_copy_child_tests_from_altohami.php`** - Copies child tests data from altohami.child_tests

### 2. Pricing Migrations
- **`2025_10_15_010002_copy_cash_prices_from_company_tests_relation.php`** - Copies cash prices (insu_id = 1) from company_tests_relation
- **`2025_10_15_010456_update_prices_without_conversion_from_altohami.php`** - Updates main tests prices without conversion

### 3. User & Doctor Migrations
- **`2025_10_15_010706_copy_doctors_from_altohami.php`** - Copies doctors data from altohami.doctors
- **`2025_10_15_013614_copy_users_from_altohami.php`** - Copies users data from altohami.users
- **`2025_10_15_013935_copy_specialists_from_doc_specialists.php`** - Copies specialists from altohami.doc_specialists
- **`2025_10_15_014036_update_doctors_specialist_ids_from_altohami.php`** - Updates doctors with correct specialist assignments

### 4. Service & Group Migrations
- **`2025_10_15_011251_copy_group_specialist_to_service_groups.php`** - Copies group_specialist to service_groups
- **`2025_10_15_011320_copy_services_from_altohami.php`** - Copies services data from altohami.services
- **`2025_10_15_011714_copy_service_prices_from_company_service_relation.php`** - Copies service prices (insu_id = 1)

### 5. Company & Contract Migrations
- **`2025_10_15_012018_copy_insurance_to_companies_from_altohami.php`** - Copies insurance data to companies table
- **`2025_10_15_012432_copy_contracts_from_company_service_relation.php`** - Copies service contracts (insu_id != 1)
- **`2025_10_15_013229_copy_contracts_from_company_tests_relation.php`** - Copies test contracts (insu_id != 1)

## Migration Order

These migrations should be run in the following order:

1. **Core Data**: main_tests → child_tests
2. **Pricing**: cash prices → price updates
3. **Users & Doctors**: specialists → doctors → users → doctor specialist updates
4. **Services**: service_groups → services → service prices
5. **Companies & Contracts**: companies → service contracts → test contracts

## Data Summary

- **Main Tests**: Copied with filtered columns (id, main_test_name, pack_id, pageBreak, container_id)
- **Child Tests**: Copied with column mapping (main_id → main_test_id, Unit → unit_id)
- **Doctors**: Copied with specialist validation and default mapping
- **Users**: Copied with password hashing and user type mapping
- **Specialists**: Copied with name mapping (speci_name → name)
- **Services**: Copied with service group validation
- **Companies**: Copied from insurance table with default values
- **Contracts**: Copied with foreign key validation

## Notes

- All migrations include duplicate prevention
- Foreign key constraints are handled with validation
- Default values are provided for required fields
- Passwords are properly hashed for security
- User types are mapped to Arabic enum values
- Prices are copied without conversion as requested
