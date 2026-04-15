<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('jaga.tables.model_permission'), function (Blueprint $table) {
            $table->morphs('model');
            $table->unsignedBigInteger('permission_id')->nullable();
            $table->string('wildcard')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'pgsql'])) {
            $table = config('jaga.tables.model_permission');
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT mp_mutex CHECK (
                (permission_id IS NOT NULL AND wildcard IS NULL) OR
                (permission_id IS NULL AND wildcard IS NOT NULL)
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('jaga.tables.model_permission'));
    }
};
