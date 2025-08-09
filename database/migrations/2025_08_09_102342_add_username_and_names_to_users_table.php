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
            $table->string('firstname')->after('name')->nullable();
            $table->string('lastname')->after('firstname')->nullable();
            $table->string('username')->unique()->after('email')->nullable();
            $table->dropColumn('name'); // This line is optional, but it's good practice to remove the 'name' column if you're replacing it with 'firstname' and 'lastname'.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name');
            $table->dropColumn('firstname');
            $table->dropColumn('lastname');
            $table->dropColumn('username');
        });
    }
};