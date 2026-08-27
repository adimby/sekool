<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_schedules', function (Blueprint $table): void {
            $table->uuid('copied_from_schedule_id')->nullable();
            $table->string('adjustment_type')->nullable();
            $table->bigInteger('adjustment_amount')->nullable();
            $table->integer('adjustment_percent_bps')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('submitted_by_person_id')->nullable()->constrained('persons');
            $table->timestamp('locked_at')->nullable();
            $table->foreignUuid('locked_by_person_id')->nullable()->constrained('persons');
            $table->timestamp('unlock_requested_at')->nullable();
            $table->foreignUuid('unlock_requested_by_person_id')->nullable()->constrained('persons');
            $table->text('unlock_request_reason')->nullable();
        });

        DB::statement('ALTER TABLE fee_schedules ADD CONSTRAINT fee_schedules_copied_from_fk FOREIGN KEY (school_id, copied_from_schedule_id) REFERENCES fee_schedules (school_id, id)');

        DB::statement('CREATE UNIQUE INDEX fee_schedules_year_grade_unique ON fee_schedules (school_id, school_year_id, grade_level_id) WHERE grade_level_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX fee_schedules_year_schoolwide_unique ON fee_schedules (school_id, school_year_id) WHERE grade_level_id IS NULL');

        DB::table('fee_schedules')
            ->where('status', 'active')
            ->whereNull('locked_at')
            ->update([
                'submitted_at' => DB::raw('created_at'),
                'locked_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fee_schedules DROP CONSTRAINT IF EXISTS fee_schedules_copied_from_fk');
        DB::statement('DROP INDEX IF EXISTS fee_schedules_year_grade_unique');
        DB::statement('DROP INDEX IF EXISTS fee_schedules_year_schoolwide_unique');

        Schema::table('fee_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by_person_id');
            $table->dropConstrainedForeignId('locked_by_person_id');
            $table->dropConstrainedForeignId('unlock_requested_by_person_id');
            $table->dropColumn([
                'copied_from_schedule_id',
                'adjustment_type',
                'adjustment_amount',
                'adjustment_percent_bps',
                'submitted_at',
                'locked_at',
                'unlock_requested_at',
                'unlock_request_reason',
            ]);
        });
    }
};
