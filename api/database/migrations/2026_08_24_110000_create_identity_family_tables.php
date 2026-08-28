<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('role');
            $table->timestampTz('acquired_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['person_id', 'role', 'acquired_at']);
        });

        Schema::create('relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subject_person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignUuid('object_person_id')->constrained('persons')->restrictOnDelete();
            $table->string('type');
            $table->jsonb('scopes')->nullable();
            $table->string('status')->default('active');
            $table->string('verification_method')->default('family_approved');
            $table->foreignUuid('verified_by_person_id')->nullable()->constrained('persons');
            $table->timestampTz('established_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['object_person_id', 'status']);
        });

        DB::statement('ALTER TABLE relationships ADD CONSTRAINT relationships_not_self CHECK (subject_person_id <> object_person_id)');
        DB::statement("CREATE UNIQUE INDEX relationships_active_unique ON relationships (subject_person_id, object_person_id, type) WHERE status <> 'revoked'");

        Schema::create('families', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label')->nullable();
            $table->string('primary_language', 5)->default('fr');
            $table->foreignUuid('created_by_person_id')->nullable()->constrained('persons');
            $table->timestamps();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('role_in_family');
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->timestamps();
        });

        Schema::create('school_person_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('kind');
            $table->string('source');
            $table->boolean('grants_contact_access')->default(false);
            $table->timestampTz('established_at');
            $table->timestamps();
            $table->unique(['school_id', 'person_id', 'kind']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'person_id']);
            $table->index('school_id');
        });

        Schema::create('family_share_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by_person_id')->constrained('persons')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->jsonb('child_person_ids');
            $table->jsonb('scopes');
            $table->foreignUuid('target_school_id')->nullable()->constrained('schools');
            $table->timestampTz('expires_at');
            $table->timestampTz('redeemed_at')->nullable();
            $table->foreignUuid('redeemed_by_school_id')->nullable()->constrained('schools');
            $table->foreignUuid('redeemed_by_person_id')->nullable()->constrained('persons');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('person_link_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('submitted_public_id_hash', 64);
            $table->foreignUuid('matched_person_id')->nullable()->constrained('persons');
            $table->string('status')->default('pending');
            $table->foreignUuid('requested_by_person_id')->constrained('persons');
            $table->string('ip_hash', 64)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index('school_id');
            $table->index(['matched_person_id', 'status']);
        });

        Schema::create('parent_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->foreignUuid('created_by_person_id')->constrained('persons');
            $table->timestampTz('expires_at');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_invitations');
        Schema::dropIfExists('person_link_requests');
        Schema::dropIfExists('family_share_tokens');
        Schema::dropIfExists('school_person_links');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('families');
        Schema::dropIfExists('relationships');
        Schema::dropIfExists('person_roles');
    }
};
