<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('jaga.tables.permissions'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('methods');
            $table->string('uri');
            $table->string('description')->nullable();
            $table->boolean('is_auto_description')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_public')->default(false);
            $table->string('group')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('jaga.tables.permissions'));
    }
};
