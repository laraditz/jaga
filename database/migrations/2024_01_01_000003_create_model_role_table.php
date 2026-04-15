<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('jaga.tables.model_role'), function (Blueprint $table) {
            $table->morphs('model');
            $table->foreignId('role_id')->constrained(config('jaga.tables.roles'))->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('jaga.tables.model_role'));
    }
};
