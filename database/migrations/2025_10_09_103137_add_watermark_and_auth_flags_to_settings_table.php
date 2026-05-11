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
			$table->string('watermark_image')->nullable();
			$table->boolean('send_sms_after_auth')->default(false);
			$table->boolean('send_whatsapp_after_auth')->default(false);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('settings', function (Blueprint $table) {
			$table->dropColumn(['watermark_image', 'send_sms_after_auth', 'send_whatsapp_after_auth']);
		});
	}
};
