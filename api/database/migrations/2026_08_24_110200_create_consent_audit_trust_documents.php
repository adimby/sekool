<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subject_person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignUuid('granted_by_person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignUuid('grantee_school_id')->constrained('schools')->restrictOnDelete();
            $table->string('scope');
            $table->text('purpose');
            $table->timestampTz('granted_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('source')->default('app');
            $table->string('terms_version')->default('1');
            $table->timestamps();
            $table->index(['subject_person_id', 'grantee_school_id', 'scope']);
        });

        Schema::create('consent_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consent_id')->constrained('consents')->restrictOnDelete();
            $table->string('event');
            $table->timestampTz('occurred_at');
            $table->foreignUuid('actor_person_id')->nullable()->constrained('persons');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('occurred_at');
            $table->foreignUuid('actor_person_id')->nullable()->constrained('persons');
            $table->uuid('actor_school_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('resource_type');
            $table->uuid('resource_id')->nullable();
            $table->uuid('subject_person_id')->nullable();
            $table->jsonb('context')->nullable();
            $table->string('outcome')->default('allowed');
            $table->timestamps();
            $table->index(['subject_person_id', 'occurred_at']);
            $table->index(['actor_school_id', 'occurred_at']);
        });

        Schema::create('trust_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->uuid('school_id')->nullable();
            $table->string('event_type');
            $table->timestampTz('occurred_at');
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'occurred_at']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('owner_person_id')->constrained('persons')->restrictOnDelete();
            $table->string('type')->default('other');
            $table->string('source_type');
            $table->string('source_school_label')->nullable();
            $table->uuid('issuer_school_id')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->foreignUuid('uploaded_by_person_id')->constrained('persons');
            $table->timestampTz('uploaded_at');
            $table->string('storage_key')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedInteger('byte_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->jsonb('provenance')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('trust_events');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('consent_events');
        Schema::dropIfExists('consents');
    }
};
