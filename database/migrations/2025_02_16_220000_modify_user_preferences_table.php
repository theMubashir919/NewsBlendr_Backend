<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            // Drop the old foreign key columns
            $table->dropForeign(['source_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['author_id']);
            $table->dropColumn(['source_id', 'category_id', 'author_id']);

            // Add new JSON columns for arrays
            $table->json('preferred_sources')->nullable();
            $table->json('preferred_categories')->nullable();
            $table->json('preferred_authors')->nullable();
            $table->boolean('email_notifications')->default(false);
            $table->enum('notification_frequency', ['daily', 'weekly', 'never'])->default('never');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            // Remove the new columns
            $table->dropColumn([
                'preferred_sources',
                'preferred_categories',
                'preferred_authors',
                'email_notifications',
                'notification_frequency'
            ]);

            // Restore the old columns
            $table->foreignId('source_id')->nullable()->constrained('sources')->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('author_id')->nullable()->constrained('authors')->onDelete('set null');
        });
    }
}; 