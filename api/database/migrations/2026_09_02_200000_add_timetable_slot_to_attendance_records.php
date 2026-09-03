<?php

use App\Domain\Platform\Tenancy\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        TenantSchema::ensureCompositePrimary('timetable_slots');

        if (! Schema::hasColumn('attendance_records', 'timetable_slot_id')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                $table->uuid('timetable_slot_id')->nullable();
                $table->index(['school_id', 'timetable_slot_id']);
            });
        }

        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_school_id_enrollment_id_date_session_unique');
        DB::statement('DROP INDEX IF EXISTS attendance_records_school_id_enrollment_id_date_session_unique');
        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_slot_fk');

        DB::statement('ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_slot_fk FOREIGN KEY (school_id, timetable_slot_id) REFERENCES timetable_slots (school_id, id) MATCH SIMPLE');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS attendance_records_day_unique ON attendance_records (school_id, enrollment_id, date, session) WHERE timetable_slot_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS attendance_records_slot_unique ON attendance_records (school_id, enrollment_id, date, timetable_slot_id) WHERE timetable_slot_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_records_slot_unique');
        DB::statement('DROP INDEX IF EXISTS attendance_records_day_unique');
        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_slot_fk');

        if (Schema::hasColumn('attendance_records', 'timetable_slot_id')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                $table->dropIndex(['school_id', 'timetable_slot_id']);
                $table->dropColumn('timetable_slot_id');
            });
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS attendance_records_school_id_enrollment_id_date_session_unique ON attendance_records (school_id, enrollment_id, date, session)');
    }
};
