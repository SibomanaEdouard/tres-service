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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('folder_id')->nullable()->constrained('folders')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path'); // Internal path to stored file
            $table->string('file_type'); // MIME type
            $table->bigInteger('file_size'); // Size in bytes
            $table->unsignedBigInteger('total_downloads')->default(0);
            $table->timestamp('last_access_date')->nullable();
            $table->timestamps();
            $table->softDeletes(); // For trash functionality
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};