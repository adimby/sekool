<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_id', 10)->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->string('birth_date_precision')->nullable();
            $table->string('sex')->default('unspecified');
            $table->string('preferred_language', 5)->default('fr');
            $table->string('phone_e164')->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->uuid('merged_into_person_id')->nullable();
            $table->date('deceased_at')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->foreign('merged_into_person_id')->references('id')->on('persons');
        });

        DB::statement("ALTER TABLE persons ADD CONSTRAINT persons_public_id_format CHECK (public_id ~ '^7[0-9]{8}[ABCDEFGHJKLMNPRSTUVWXYZ]$')");
        DB::statement('CREATE INDEX persons_last_name_trgm ON persons USING gin (last_name gin_trgm_ops)');

        Schema::create('user_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->unique()->constrained('persons');
            $table->string('email')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('password');
            $table->text('totp_secret_encrypted')->nullable();
            $table->timestampTz('totp_enabled_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX user_accounts_email_unique ON user_accounts (email) WHERE email IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX user_accounts_phone_unique ON user_accounts (phone_e164) WHERE phone_e164 IS NOT NULL');
        DB::statement('ALTER TABLE user_accounts ADD CONSTRAINT user_accounts_identifier CHECK (email IS NOT NULL OR phone_e164 IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_accounts');
        Schema::dropIfExists('persons');
    }
};
