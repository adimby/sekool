<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->uuid('payer_account_id')->nullable();
            $table->string('level');
            $table->bigInteger('outstanding_amount')->default(0);
            $table->unsignedInteger('days_overdue')->default(0);
            $table->decimal('on_time_ratio', 5, 4)->nullable();
            $table->string('calculator_version');
            $table->timestampTz('computed_at');
            $table->string('manual_override_level')->nullable();
            $table->string('override_reason')->nullable();
            $table->timestampTz('override_until')->nullable();
            $table->uuid('override_by_person_id')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'enrollment_id']);
            $table->index(['school_id', 'level']);
        });

        DB::statement('ALTER TABLE risk_assessments ADD CONSTRAINT risk_assessments_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement("ALTER TABLE risk_assessments ADD CONSTRAINT risk_assessments_level_chk CHECK (level IN ('low', 'medium', 'high', 'critical'))");
        DB::statement('ALTER TABLE risk_assessments ADD CONSTRAINT risk_assessments_override_chk CHECK (manual_override_level IS NULL OR (override_reason IS NOT NULL AND override_until IS NOT NULL))');

        Schema::create('risk_factors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('risk_assessment_id');
            $table->string('factor_key');
            $table->string('human_label');
            $table->integer('contribution');
            $table->jsonb('evidence')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'risk_assessment_id']);
        });

        DB::statement('ALTER TABLE risk_factors ADD CONSTRAINT risk_factors_assessment_fk FOREIGN KEY (school_id, risk_assessment_id) REFERENCES risk_assessments (school_id, id)');

        Schema::create('collection_forecasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->date('week_starting_on');
            $table->bigInteger('expected_amount');
            $table->bigInteger('confidence_low_amount');
            $table->bigInteger('confidence_high_amount');
            $table->string('method_version');
            $table->timestampTz('computed_at');
            $table->jsonb('breakdown')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'week_starting_on']);
        });

        Schema::create('collection_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->string('template_key');
            $table->string('title');
            $table->text('reason_summary');
            $table->string('priority');
            $table->string('status')->default('open');
            $table->uuid('workflow_run_id')->nullable();
            $table->uuid('claimed_by_person_id')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolution_notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status', 'priority']);
            $table->index(['school_id', 'enrollment_id']);
        });

        DB::statement('ALTER TABLE collection_tasks ADD CONSTRAINT collection_tasks_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement("ALTER TABLE collection_tasks ADD CONSTRAINT collection_tasks_status_chk CHECK (status IN ('open', 'in_progress', 'resolved', 'dismissed'))");

        Schema::create('workflow_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('template_key');
            $table->boolean('enabled')->default(true);
            $table->jsonb('params')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('dry_run')->default(true);
            $table->unsignedInteger('daily_action_cap')->default(20);
            $table->jsonb('quiet_hours')->nullable();
            $table->uuid('updated_by_person_id')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'template_key']);
        });

        DB::statement('ALTER TABLE workflow_rules ADD CONSTRAINT workflow_rules_cap_chk CHECK (daily_action_cap >= 1 AND daily_action_cap <= 50)');

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('rule_id');
            $table->string('trigger_event_type');
            $table->uuid('trigger_event_id')->nullable();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('idempotency_key');
            $table->string('status');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'rule_id', 'idempotency_key']);
        });

        DB::statement('ALTER TABLE workflow_runs ADD CONSTRAINT workflow_runs_rule_fk FOREIGN KEY (school_id, rule_id) REFERENCES workflow_rules (school_id, id)');

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('run_id');
            $table->string('type');
            $table->string('status');
            $table->jsonb('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'run_id']);
        });

        DB::statement('ALTER TABLE workflow_actions ADD CONSTRAINT workflow_actions_run_fk FOREIGN KEY (school_id, run_id) REFERENCES workflow_runs (school_id, id)');

        Schema::create('message_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('key');
            $table->string('channel');
            $table->string('locale', 8)->default('fr');
            $table->string('subject');
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'key', 'channel', 'locale']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('template_key');
            $table->uuid('subject_person_id');
            $table->uuid('recipient_person_id');
            $table->string('channel');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('sent_at')->nullable();
            $table->string('priority')->default('normal');
            $table->uuid('workflow_run_id')->nullable();
            $table->string('idempotency_key');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'idempotency_key']);
            $table->index(['school_id', 'channel', 'queued_at']);
            $table->index(['recipient_person_id', 'queued_at']);
        });

        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('message_id');
            $table->string('status');
            $table->timestampTz('occurred_at');
            $table->string('provider_reference')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'message_id']);
        });

        DB::statement('ALTER TABLE message_deliveries ADD CONSTRAINT message_deliveries_message_fk FOREIGN KEY (school_id, message_id) REFERENCES messages (school_id, id)');

        Schema::create('reliability_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->uuid('school_id')->nullable();
            $table->string('index_type');
            $table->unsignedSmallInteger('value');
            $table->string('band');
            $table->string('calculator_version');
            $table->timestampTz('computed_at');
            $table->string('inputs_digest');
            $table->unsignedInteger('event_count')->default(0);
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'index_type']);
            $table->index(['school_id', 'index_type']);
        });

        Schema::create('reliability_score_factors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('score_id');
            $table->string('event_type');
            $table->string('human_label');
            $table->integer('contribution');
            $table->unsignedInteger('event_count')->default(0);
            $table->jsonb('sample_event_ids')->nullable();
            $table->timestamps();

            $table->index('score_id');
        });

        DB::statement('ALTER TABLE reliability_score_factors ADD CONSTRAINT reliability_score_factors_score_fk FOREIGN KEY (score_id) REFERENCES reliability_scores (id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reliability_score_factors');
        Schema::dropIfExists('reliability_scores');
        Schema::dropIfExists('message_deliveries');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflow_rules');
        Schema::dropIfExists('collection_tasks');
        Schema::dropIfExists('collection_forecasts');
        Schema::dropIfExists('risk_factors');
        Schema::dropIfExists('risk_assessments');
    }
};
