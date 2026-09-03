<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use App\Domain\Platform\Tenancy\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keep later tables if one constraint fails on an old demo volume. */
    public $withinTransaction = false;

    /** @var list<string> */
    private const TABLES = [
        'class_posts',
        'disciplinary_cases',
        'school_events',
    ];

    public function up(): void
    {
        TenantSchema::ensureCompositePrimary('classrooms');
        TenantSchema::ensureCompositePrimary('grade_levels');
        TenantSchema::ensureCompositePrimary('enrollments');

        if (! Schema::hasTable('class_posts')) {
            Schema::create('class_posts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
                $table->uuid('classroom_id');
                $table->string('kind');
                $table->string('title');
                $table->text('body');
                $table->date('due_on')->nullable();
                $table->date('held_on')->nullable();
                $table->string('attachment_name')->nullable();
                $table->text('attachment_body')->nullable();
                $table->foreignUuid('created_by_person_id')->constrained('persons')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'id']);
                $table->index(['school_id', 'classroom_id', 'kind']);
            });

            DB::statement('ALTER TABLE class_posts ADD CONSTRAINT class_posts_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');
            DB::statement("ALTER TABLE class_posts ADD CONSTRAINT class_posts_kind_chk CHECK (kind IN ('homework', 'journal'))");
            DB::statement("ALTER TABLE class_posts ADD CONSTRAINT class_posts_dates_chk CHECK (
                (kind = 'homework' AND due_on IS NOT NULL AND held_on IS NULL)
                OR (kind = 'journal' AND held_on IS NOT NULL AND due_on IS NULL)
            )");
        }

        if (! Schema::hasTable('disciplinary_cases')) {
            Schema::create('disciplinary_cases', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
                $table->uuid('enrollment_id');
                $table->uuid('classroom_id');
                $table->date('occurred_on');
                $table->text('fact');
                $table->string('measure_type');
                $table->string('measure_label');
                $table->date('measure_on')->nullable();
                $table->string('status')->default('open');
                $table->text('follow_up')->nullable();
                $table->foreignUuid('created_by_person_id')->constrained('persons')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'id']);
                $table->index(['school_id', 'classroom_id']);
                $table->index(['school_id', 'enrollment_id']);
            });

            DB::statement('ALTER TABLE disciplinary_cases ADD CONSTRAINT disciplinary_cases_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');
            DB::statement('ALTER TABLE disciplinary_cases ADD CONSTRAINT disciplinary_cases_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
            DB::statement("ALTER TABLE disciplinary_cases ADD CONSTRAINT disciplinary_cases_measure_chk CHECK (measure_type IN ('warning', 'detention', 'meeting', 'other'))");
            DB::statement("ALTER TABLE disciplinary_cases ADD CONSTRAINT disciplinary_cases_status_chk CHECK (status IN ('open', 'done', 'cancelled'))");
        }

        if (! Schema::hasTable('school_events')) {
            Schema::create('school_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
                $table->string('type');
                $table->string('title');
                $table->text('body')->nullable();
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->string('audience');
                $table->uuid('grade_level_id')->nullable();
                $table->uuid('classroom_id')->nullable();
                $table->string('location')->nullable();
                $table->foreignUuid('created_by_person_id')->constrained('persons')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'id']);
                $table->index(['school_id', 'starts_on']);
                $table->index(['school_id', 'classroom_id']);
                $table->index(['school_id', 'grade_level_id']);
            });

            DB::statement('ALTER TABLE school_events ADD CONSTRAINT school_events_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id) MATCH SIMPLE');
            DB::statement('ALTER TABLE school_events ADD CONSTRAINT school_events_grade_fk FOREIGN KEY (school_id, grade_level_id) REFERENCES grade_levels (school_id, id) MATCH SIMPLE');
            DB::statement("ALTER TABLE school_events ADD CONSTRAINT school_events_type_chk CHECK (type IN ('meeting', 'open_day', 'tournament', 'other'))");
            DB::statement("ALTER TABLE school_events ADD CONSTRAINT school_events_audience_chk CHECK (audience IN ('school', 'grade', 'classroom'))");
            DB::statement("ALTER TABLE school_events ADD CONSTRAINT school_events_audience_target_chk CHECK (
                (audience = 'school' AND grade_level_id IS NULL AND classroom_id IS NULL)
                OR (audience = 'grade' AND grade_level_id IS NOT NULL AND classroom_id IS NULL)
                OR (audience = 'classroom' AND classroom_id IS NOT NULL)
            )");
            DB::statement('ALTER TABLE school_events ADD CONSTRAINT school_events_dates_chk CHECK (ends_on IS NULL OR ends_on >= starts_on)');
        }

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                RowLevelSecurity::enable($table);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
