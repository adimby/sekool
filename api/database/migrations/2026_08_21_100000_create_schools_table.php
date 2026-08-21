<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('network_id')->nullable();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('code')->unique();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('email')->nullable();
            $table->string('timezone')->default('Indian/Antananarivo');
            $table->char('currency', 3)->default('MGA');
            $table->string('locale', 10)->default('fr');
            $table->string('status')->default('active');
            $table->string('plan')->default('starter');
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
