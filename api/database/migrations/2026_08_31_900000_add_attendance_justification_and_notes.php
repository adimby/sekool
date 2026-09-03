<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attendance_records', 'justification')) {
            return;
        }

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->string('justification', 500)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('attendance_records', 'justification')) {
            return;
        }

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropColumn('justification');
        });
    }
};
