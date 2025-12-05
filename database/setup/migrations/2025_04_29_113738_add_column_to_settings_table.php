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
        Schema::table('settings', function (Blueprint $table) {
            $table->text('welcome_message')->nullable()->default('👋 مرحباً بكم في مستشفى الرومي للأسنان! ✨

🦷 يسعدنا اختياركم لنا للعناية بصحة أسنانكم.

👨‍⚕️👩‍⚕️ فريقنا المتخصص ملتزم بتقديم خدمات استثنائية في بيئة مريحة.

😁 ابتسامتكم هي أولويتنا!

📱 للاستفسارات، يرجى التواصل معنا وسنكون سعداء بالرد على استفساراتكم.

🙏 شكراً لثقتكم بنا.');
$table->boolean('send_welcome_message');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('welcome_message');
        });
    }
};
