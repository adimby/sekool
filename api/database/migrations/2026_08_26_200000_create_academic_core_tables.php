<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('school_year_id');
            $table->string('label');
            $table->unsignedSmallInteger('sequence');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'school_year_id', 'sequence']);
            $table->index(['school_id', 'school_year_id']);
        });

        DB::statement('ALTER TABLE academic_terms ADD CONSTRAINT academic_terms_year_fk FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)');

        Schema::create('grade_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('name');
            $table->string('stage');
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'sequence']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('school_year_id');
            $table->uuid('grade_level_id');
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->foreignUuid('main_teacher_person_id')->nullable()->constrained('persons');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'school_year_id', 'name']);
            $table->index(['school_id', 'school_year_id']);
        });

        DB::statement('ALTER TABLE classrooms ADD CONSTRAINT classrooms_year_fk FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)');
        DB::statement('ALTER TABLE classrooms ADD CONSTRAINT classrooms_grade_fk FOREIGN KEY (school_id, grade_level_id) REFERENCES grade_levels (school_id, id)');

        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_classroom_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->date('date');
            $table->string('session');
            $table->string('status');
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->string('reason')->nullable();
            $table->foreignUuid('recorded_by_person_id')->constrained('persons');
            $table->string('recorded_via')->default('web');
            $table->uuid('client_reference')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'enrollment_id', 'date', 'session']);
            $table->index(['school_id', 'date', 'status']);
        });

        DB::statement('ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement('CREATE UNIQUE INDEX attendance_records_client_reference_unique ON attendance_records (school_id, client_reference) WHERE client_reference IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS enrollments_classroom_fk');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('academic_terms');
    }
};
