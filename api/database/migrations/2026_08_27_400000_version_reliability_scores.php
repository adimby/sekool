<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reliability_scores')
            ->where('index_type', 'family')
            ->update(['index_type' => 'family_reliability']);

        Schema::table('reliability_scores', function (Blueprint $table): void {
            $table->unique(
                ['school_id', 'subject_type', 'subject_id', 'index_type', 'calculator_version'],
                'reliability_scores_subject_version_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reliability_scores', function (Blueprint $table): void {
            $table->dropUnique('reliability_scores_subject_version_unique');
        });

        DB::table('reliability_scores')
            ->where('index_type', 'family_reliability')
            ->update(['index_type' => 'family']);
    }
};
