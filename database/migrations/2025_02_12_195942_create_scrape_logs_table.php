<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scrape_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->onDelete('cascade');
            $table->enum('status', ['success', 'failed']);
            $table->integer('articles_added');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_logs');
    }
};