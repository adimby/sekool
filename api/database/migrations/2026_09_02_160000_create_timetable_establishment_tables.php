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
        'timetable_substitutions',
        'exam_sessions',
    ];

    public function up(): void
    {
        Schema::create('timetable_substitutions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('timetable_slot_id');
            $table->uuid('classroom_id');
            $table->date('on_date');
            $table->foreignUuid('substitute_person_id')->nullable()->constrained('persons')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by_person_id')->constrained('persons')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'timetable_slot_id', 'on_date'], 'timetable_substitutions_slot_date_uidx');
            $table->index(['school_id', 'classroom_id', 'on_date']);
        });

        DB::statement('ALTER TABLE timetable_substitutions ADD CONSTRAINT timetable_substitutions_slot_fk FOREIGN KEY (school_id, timetable_slot_id) REFERENCES timetable_slots (school_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE timetable_substitutions ADD CONSTRAINT timetable_substitutions_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');

        Schema::create('exam_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('classroom_id');
            $table->string('title');
            $table->string('subject')->nullable();
            $table->date('held_on');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room')->nullable();
            $table->text('body')->nullable();
            $table->foreignUuid('created_by_person_id')->constrained('persons')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'classroom_id', 'held_on']);
            $table->index(['school_id', 'held_on']);
        });

        DB::statement('ALTER TABLE exam_sessions ADD CONSTRAINT exam_sessions_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');
        DB::statement('ALTER TABLE exam_sessions ADD CONSTRAINT exam_sessions_time_chk CHECK (ends_at > starts_at)');

        foreach (self::TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
