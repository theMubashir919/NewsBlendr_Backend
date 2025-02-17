<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->enum('api_type', ['newsapi', 'guardian', 'nytimes']);
            $table->string('api_endpoint', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
