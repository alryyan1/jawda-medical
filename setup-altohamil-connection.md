# Altohamil Database Connection Setup

## Environment Variables Required

Add the following environment variables to your `.env` file:

```env
# Altohamil Database Connection (for copying data from altohami database)
ALTOHAMIL_DB_HOST=127.0.0.1
ALTOHAMIL_DB_PORT=3306
ALTOHAMIL_DB_DATABASE=altohami
ALTOHAMIL_DB_USERNAME=your_username_here
ALTOHAMIL_DB_PASSWORD=your_password_here
```

## Steps to Copy Data

1. **Update your `.env` file** with the correct database credentials for the altohami database
2. **Run the migrations** to copy the data:
   ```bash
   php artisan migrate
   ```

## What the Migrations Do

- **copy_main_tests_from_altohami**: Copies all data from `altohami.main_tests` to your current database's `main_tests` table
- **copy_child_tests_from_altohami**: Copies all data from `altohami.child_tests` to your current database's `child_tests` table

## Important Notes

- The migrations check for existing records to avoid duplicates
- If a record with the same ID already exists, it will be skipped
- The migrations will show progress messages during execution
- Make sure both databases are accessible and the tables exist before running the migrations

## Troubleshooting

If you encounter connection issues:
1. Verify the database credentials in your `.env` file
2. Ensure the altohami database is accessible
3. Check that the `main_tests` and `child_tests` tables exist in both databases
4. Make sure your current database (alroomy) has the corresponding tables with the same structure
