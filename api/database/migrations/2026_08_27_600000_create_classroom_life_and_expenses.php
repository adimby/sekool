<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'classroom_teachers',
        'timetable_slots',
        'class_councils',
        'class_activities',
        'school_expenses',
    ];

    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->foreignUuid('delegate_person_id')->nullable()->constrained('persons');
            $table->foreignUuid('vice_delegate_person_id')->nullable()->constrained('persons');
        });

        Schema::create('classroom_teachers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('classroom_id');
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('subject')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'classroom_id', 'person_id']);
            $table->index(['school_id', 'classroom_id']);
        });

        DB::statement('ALTER TABLE classroom_teachers ADD CONSTRAINT classroom_teachers_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');

        Schema::create('timetable_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('classroom_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('subject');
            $table->foreignUuid('teacher_person_id')->nullable()->constrained('persons');
            $table->string('room')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'classroom_id', 'weekday']);
        });

        DB::statement('ALTER TABLE timetable_slots ADD CONSTRAINT timetable_slots_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');
        DB::statement('ALTER TABLE timetable_slots ADD CONSTRAINT timetable_slots_weekday_chk CHECK (weekday BETWEEN 1 AND 6)');
        DB::statement('ALTER TABLE timetable_slots ADD CONSTRAINT timetable_slots_time_chk CHECK (ends_at > starts_at)');

        Schema::create('class_councils', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('classroom_id');
            $table->uuid('academic_term_id')->nullable();
            $table->date('held_on');
            $table->string('title');
            $table->text('minutes')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'classroom_id']);
        });

        DB::statement('ALTER TABLE class_councils ADD CONSTRAINT class_councils_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');
        DB::statement('ALTER TABLE class_councils ADD CONSTRAINT class_councils_term_fk FOREIGN KEY (school_id, academic_term_id) REFERENCES academic_terms (school_id, id)');

        Schema::create('class_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('classroom_id');
            $table->string('type');
            $table->string('title');
            $table->date('held_on');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'classroom_id']);
        });

        DB::statement('ALTER TABLE class_activities ADD CONSTRAINT class_activities_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');

        Schema::create('school_expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('school_year_id');
            $table->string('kind');
            $table->string('label');
            $table->string('category')->default('other');
            $table->bigInteger('amount');
            $table->date('spent_on');
            $table->string('vendor')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by_person_id')->constrained('persons');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'school_year_id']);
        });

        DB::statement('ALTER TABLE school_expenses ADD CONSTRAINT school_expenses_year_fk FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)');
        DB::statement('ALTER TABLE school_expenses ADD CONSTRAINT school_expenses_amount_chk CHECK (amount > 0)');

        foreach (self::TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            Schema::dropIfExists($table);
        }

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delegate_person_id');
            $table->dropConstrainedForeignId('vice_delegate_person_id');
        });
    }
};
