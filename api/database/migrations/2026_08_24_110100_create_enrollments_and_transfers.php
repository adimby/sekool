<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->uuid('classroom_id')->nullable();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('student_number')->nullable();
            $table->string('status')->default('active');
            $table->date('enrolled_on');
            $table->date('ended_on')->nullable();
            $table->string('exit_reason')->nullable();
            $table->string('source_type')->default('native');
            $table->timestamps();

            $table->unique(['school_id', 'school_year_id', 'person_id']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status', 'school_year_id']);
        });

        DB::statement("CREATE UNIQUE INDEX enrollments_one_active_per_person ON enrollments (person_id) WHERE status = 'active'");
        DB::statement('CREATE UNIQUE INDEX enrollments_student_number_unique ON enrollments (school_id, student_number) WHERE student_number IS NOT NULL');

        Schema::create('enrollment_status_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason')->nullable();
            $table->timestampTz('occurred_at');
            $table->foreignUuid('actor_person_id')->nullable()->constrained('persons');
            $table->timestamps();
            $table->index('school_id');
        });

        DB::statement('ALTER TABLE enrollment_status_changes ADD CONSTRAINT enrollment_status_changes_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');

        Schema::create('enrollment_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignUuid('origin_school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('origin_enrollment_id');
            $table->foreignUuid('destination_school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('destination_enrollment_id')->nullable();
            $table->foreignUuid('requested_by_person_id')->constrained('persons');
            $table->timestampTz('parent_approved_at')->nullable();
            $table->foreignUuid('parent_approved_by_person_id')->nullable()->constrained('persons');
            $table->timestampTz('origin_school_approved_at')->nullable();
            $table->foreignUuid('origin_approved_by_person_id')->nullable()->constrained('persons');
            $table->string('status')->default('pending_parent');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('external_education_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('school_label');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('declared_grade_level')->nullable();
            $table->foreignUuid('declared_by_person_id')->constrained('persons');
            $table->string('verification_status')->default('unverified');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_education_periods');
        Schema::dropIfExists('enrollment_transfers');
        Schema::dropIfExists('enrollment_status_changes');
        Schema::dropIfExists('enrollments');
    }
};
