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
        Schema::table('main_tests', function (Blueprint $table) {
            $table->boolean('divided')->default(0);
            $table->boolean('available')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_tests', function (Blueprint $table) {
             $table->dropColumn('divided');
             $table->dropColumn('available');
        });
    }
};
