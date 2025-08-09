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
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Creator of the link
            $table->string('shareable_type'); // e.g., App\Models\File, App\Models\Folder
            $table->unsignedBigInteger('shareable_id'); // ID of the file or folder
            $table->string('link')->unique(); // The actual generated shareable URL
            $table->enum('type', ['internal', 'email', 'public'])->default('public');
            $table->string('recipient_email')->nullable(); // For email shares
            $table->timestamp('expires_at')->nullable();
            $table->string('password')->nullable(); // For password-protected links
            $table->enum('permissions', ['view', 'upload-download-view'])->default('view'); // Permissions for the link
            $table->timestamps();

            // Add index for polymorphic relationship
            $table->index(['shareable_type', 'shareable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};