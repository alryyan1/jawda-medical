<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop FK columns on tables that are NOT themselves being removed.
        if (Schema::hasColumn('patients', 'country_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('country_id');
            });
        }

        if (Schema::hasColumn('doctors', 'finance_account_id') || Schema::hasColumn('doctors', 'finanace_account_id_insurance')) {
            Schema::table('doctors', function (Blueprint $table) {
                if (Schema::hasColumn('doctors', 'finance_account_id')) {
                    $table->dropForeign('doctors_finance_account_id_foreign');
                    $table->dropColumn('finance_account_id');
                }
                if (Schema::hasColumn('doctors', 'finanace_account_id_insurance')) {
                    $table->dropForeign('doctors_finanace_account_id_insurance_foreign');
                    $table->dropColumn('finanace_account_id_insurance');
                }
            });
        }

        if (Schema::hasColumn('companies', 'finance_account_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropForeign('companies_finance_account_id_foreign');
                $table->dropColumn('finance_account_id');
            });
        }

        if (Schema::hasColumn('settings', 'finance_account_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $settingsAccountColumns = [
                    'finance_account_id',
                    'bank_id',
                    'company_account_id',
                    'endurance_account_id',
                    'main_cash',
                    'main_bank',
                    'pharmacy_bank',
                    'pharmacy_cash',
                    'pharmacy_income',
                ];
                foreach ($settingsAccountColumns as $column) {
                    $table->dropForeign("settings_{$column}_foreign");
                }
                $table->dropColumn($settingsAccountColumns);
            });
        }

        if (Schema::hasColumn('costs', 'employee_id')) {
            Schema::table('costs', function (Blueprint $table) {
                $table->dropForeign('costs_employee_id_foreign');
                $table->dropColumn('employee_id');
            });
        }

        if (Schema::hasColumn('drugs_prescribed', 'item_id')) {
            Schema::table('drugs_prescribed', function (Blueprint $table) {
                $table->dropForeign('drugs_prescribed_item_id_foreign');
                $table->dropColumn('item_id');
            });
        }

        // 2. Drop tables, children before parents.
        Schema::dropIfExists('audited_requested_services');
        Schema::dropIfExists('audited_patient_records');

        Schema::dropIfExists('credit_entries');
        Schema::dropIfExists('debit_entries');
        Schema::dropIfExists('account_hierarchy');

        Schema::dropIfExists('debits');
        Schema::dropIfExists('deducted_items');
        Schema::dropIfExists('deposit_items');

        Schema::dropIfExists('employee_expenses');
        Schema::dropIfExists('employee_skills');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');

        Schema::dropIfExists('deposits');
        Schema::dropIfExists('deducts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_barcodes');

        Schema::dropIfExists('finance_entries');
        Schema::dropIfExists('finance_accounts');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('countries');

        Schema::dropIfExists('account_categories');
        Schema::dropIfExists('balance_sheet_statements');
        Schema::dropIfExists('bankak_images');
        Schema::dropIfExists('client_payments');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_states');
        Schema::dropIfExists('acon_cbc_parameters');
        Schema::dropIfExists('acon_cbc_results');
        Schema::dropIfExists('images');
        Schema::dropIfExists('income_statements');
        Schema::dropIfExists('income_statement_reports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: this ERP/HR/misc scaffolding cluster and its schema have been removed from the codebase.
    }
};
