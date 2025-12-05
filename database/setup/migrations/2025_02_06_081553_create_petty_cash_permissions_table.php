<?php
// database/migrations/xxxx_xx_xx_create_petty_cash_permissions_table.php

use App\Models\FinanceAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePettyCashPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('petty_cash_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_number')->unique(); // رقم الإذن (يمكن أن يكون فريدًا)
            $table->date('date'); // تاريخ الإذن
            $table->decimal('amount', 15, 2); // المبلغ
            $table->string('beneficiary'); // المستفيد
            $table->text('description')->nullable(); // الوصف
            $table->string('pdf_file')->nullable(); // اسم ملف PDF
            $table->foreignIdFor(FinanceAccount::class); // حساب الصرف (FK)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('petty_cash_permissions');
    }
}

