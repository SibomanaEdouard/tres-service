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
        Schema::table('users', function (Blueprint $table) {
            // These columns are for account settings
            $table->enum('default_privacy', ['public', 'private'])->default('private')->after('email');
            $table->string('avatar')->nullable()->after('default_privacy');
            $table->boolean('enable_watermark')->default(false)->after('avatar');
            $table->enum('watermark_position', ['top-left', 'top-right', 'bottom-left', 'bottom-right'])->nullable()->after('enable_watermark');
            
            // This column is for profile updates (phone number)
            $table->string('phone')->nullable()->after('username'); // Assuming 'username' is after 'name' and 'email'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_privacy');
            $table->dropColumn('avatar');
            $table->dropColumn('enable_watermark');
            $table->dropColumn('watermark_position');
            $table->dropColumn('phone');
        });
    }
};