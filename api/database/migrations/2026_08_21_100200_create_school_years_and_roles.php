<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('label');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'label']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'is_current']);
        });

        Schema::create('school_role_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('role');
            $table->timestampTz('granted_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('granted_by_person_id')->nullable()->constrained('persons');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'person_id']);
        });

        DB::statement('CREATE UNIQUE INDEX school_role_assignments_active_unique ON school_role_assignments (school_id, person_id, role) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('school_role_assignments');
        Schema::dropIfExists('school_years');
    }
};
