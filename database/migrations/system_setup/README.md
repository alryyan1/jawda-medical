# System Setup Migrations

This directory contains migration files for all 150 tables in the `alroomy_jawda` database.

**Generated on:** 2024-01-01  
**Total migrations:** 150

## Purpose

These migrations are designed for system initialization or to replicate the current database structure in a fresh environment.

## Order

Migrations are ordered by table dependencies using topological sorting:

- Independent tables (no foreign keys) are created first
- Dependent tables follow after their dependencies
- Timestamps ensure correct execution order

## Usage

### Apply all migrations:

```bash
php artisan migrate --path=database/migrations/system_setup
```

### Rollback all migrations:

```bash
php artisan migrate:rollback --path=database/migrations/system_setup
```

## ⚠️ Important Notes

- **Caution:** These migrations will create all tables from scratch
- Use only on fresh databases or for system initialization
- Do not run on existing production databases without backing up first
- Foreign keys use cascade delete by default

## Generated Files

All files follow the naming convention:

```
YYYY_MM_DD_HHMMSS_create_<table_name>_table.php
```

Files are numbered from `2024_01_01_000000` to `2024_01_01_002450`.
