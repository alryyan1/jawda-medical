<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate initial_deposit to credit transactions
        DB::statement("
            INSERT INTO admission_transactions (admission_id, type, amount, description, reference_type, is_bank, user_id, created_at, updated_at)
            SELECT 
                id,
                'credit',
                COALESCE(initial_deposit, 0),
                'أمانة أولية',
                'deposit',
                false,
                user_id,
                created_at,
                updated_at
            FROM admissions
            WHERE COALESCE(initial_deposit, 0) > 0
        ");

        // Migrate admission_deposits to credit transactions
        DB::statement("
            INSERT INTO admission_transactions (admission_id, type, amount, description, reference_type, reference_id, is_bank, notes, user_id, created_at, updated_at)
            SELECT 
                admission_id,
                'credit',
                amount,
                'أمانة إضافية',
                'deposit',
                id,
                is_bank,
                notes,
                user_id,
                created_at,
                updated_at
            FROM admission_deposits
        ");

        // Migrate services to debit transactions
        // Calculate net payable: (price * count) - discount - (price * count * discount_per / 100) - endurance
        // Note: This migration runs after payment fields are removed, so we calculate from available fields
        DB::statement("
            INSERT INTO admission_transactions (admission_id, type, amount, description, reference_type, reference_id, is_bank, user_id, created_at, updated_at)
            SELECT 
                ars.admission_id,
                'debit',
                GREATEST(0, 
                    (COALESCE(ars.price, 0) * COALESCE(ars.count, 1)) 
                    - COALESCE(ars.discount, 0) 
                    - ((COALESCE(ars.price, 0) * COALESCE(ars.count, 1)) * COALESCE(ars.discount_per, 0) / 100)
                    - COALESCE(ars.endurance, 0)
                ),
                CONCAT('خدمة: ', COALESCE(s.name, 'خدمة')),
                'service',
                ars.id,
                false,
                ars.user_id,
                ars.created_at,
                ars.updated_at
            FROM admission_requested_services ars
            LEFT JOIN services s ON s.id = ars.service_id
            WHERE (COALESCE(ars.price, 0) * COALESCE(ars.count, 1)) > 0
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete migrated transactions
        DB::table('admission_transactions')->whereIn('reference_type', ['deposit', 'service'])->delete();
    }
};

