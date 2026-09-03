<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS enrollments_student_number_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS enrollments_student_number_class_unique ON enrollments (school_id, classroom_id, student_number) WHERE student_number IS NOT NULL AND classroom_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS enrollments_student_number_school_unique ON enrollments (school_id, student_number) WHERE student_number IS NOT NULL AND classroom_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS enrollments_student_number_class_unique');
        DB::statement('DROP INDEX IF EXISTS enrollments_student_number_school_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS enrollments_student_number_unique ON enrollments (school_id, student_number) WHERE student_number IS NOT NULL');
    }
};
