<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_networks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();

            $table->unique('name');
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->foreign('network_id')->references('id')->on('school_networks')->nullOnDelete();
        });

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->string('series', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropColumn('series');
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropForeign(['network_id']);
        });

        Schema::dropIfExists('school_networks');
    }
};
