<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TENANT_TABLES = [
        'certificates',
        'certificate_verification_tokens',
        'certificate_verifications',
        'suppliers',
        'kit_definitions',
        'kit_needs',
        'kit_packs',
        'kit_pack_items',
        'kit_orders',
        'subjects',
        'grade_entries',
        'student_alerts',
        'student_alert_signals',
    ];

    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->uuid('supersedes_document_id')->nullable();
        });

        Schema::create('document_verification_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->restrictOnDelete();
            $table->uuid('school_id')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignUuid('actor_person_id')->nullable()->constrained('persons');
            $table->uuid('actor_school_id')->nullable();
            $table->string('method')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('document_id');
            $table->foreignUuid('subject_person_id')->constrained('persons')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->string('type');
            $table->string('public_reference');
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at')->nullable();
            $table->string('status')->default('valid');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->string('template_version')->default('1');
            $table->jsonb('payload_snapshot');
            $table->string('artifact_sha256', 64);
            $table->string('signer_key_id');
            $table->text('signature');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'public_reference']);
            $table->index(['school_id', 'subject_person_id']);
        });

        DB::statement('ALTER TABLE certificates ADD CONSTRAINT certificates_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');

        Schema::create('certificate_verification_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('certificate_id');
            $table->string('token_hash', 64);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique('token_hash');
        });

        DB::statement('ALTER TABLE certificate_verification_tokens ADD CONSTRAINT certificate_tokens_cert_fk FOREIGN KEY (school_id, certificate_id) REFERENCES certificates (school_id, id)');

        Schema::create('certificate_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('token_id');
            $table->timestampTz('verified_at');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('outcome');
            $table->timestamps();

            $table->index(['school_id', 'verified_at']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->unsignedInteger('commission_rate_bps')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'name']);
        });

        Schema::create('kit_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('school_year_id');
            $table->uuid('grade_level_id')->nullable();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'school_year_id']);
        });

        DB::statement('ALTER TABLE kit_definitions ADD CONSTRAINT kit_definitions_year_fk FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)');

        Schema::create('kit_needs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('kit_definition_id');
            $table->string('label');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
        });

        DB::statement('ALTER TABLE kit_needs ADD CONSTRAINT kit_needs_def_fk FOREIGN KEY (school_id, kit_definition_id) REFERENCES kit_definitions (school_id, id)');

        Schema::create('kit_packs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('kit_definition_id');
            $table->uuid('supplier_id');
            $table->string('tier');
            $table->bigInteger('total_amount');
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'kit_definition_id', 'tier']);
        });

        DB::statement('ALTER TABLE kit_packs ADD CONSTRAINT kit_packs_def_fk FOREIGN KEY (school_id, kit_definition_id) REFERENCES kit_definitions (school_id, id)');
        DB::statement('ALTER TABLE kit_packs ADD CONSTRAINT kit_packs_supplier_fk FOREIGN KEY (school_id, supplier_id) REFERENCES suppliers (school_id, id)');
        DB::statement('ALTER TABLE kit_packs ADD CONSTRAINT kit_packs_amount_chk CHECK (total_amount > 0)');

        Schema::create('kit_pack_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('kit_pack_id');
            $table->uuid('need_id')->nullable();
            $table->string('product_reference')->nullable();
            $table->bigInteger('unit_amount')->default(0);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'id']);
        });

        DB::statement('ALTER TABLE kit_pack_items ADD CONSTRAINT kit_pack_items_pack_fk FOREIGN KEY (school_id, kit_pack_id) REFERENCES kit_packs (school_id, id)');

        Schema::create('kit_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('payer_account_id')->nullable();
            $table->uuid('enrollment_id');
            $table->uuid('kit_pack_id');
            $table->uuid('supplier_id');
            $table->string('status')->default('submitted');
            $table->bigInteger('total_amount');
            $table->bigInteger('commission_amount')->default(0);
            $table->timestampTz('placed_at');
            $table->foreignUuid('placed_by_person_id')->constrained('persons');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'enrollment_id']);
        });

        DB::statement('ALTER TABLE kit_orders ADD CONSTRAINT kit_orders_enroll_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement('ALTER TABLE kit_orders ADD CONSTRAINT kit_orders_pack_fk FOREIGN KEY (school_id, kit_pack_id) REFERENCES kit_packs (school_id, id)');
        DB::statement('ALTER TABLE kit_orders ADD CONSTRAINT kit_orders_amount_chk CHECK (total_amount > 0)');

        Schema::create('subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'name']);
        });

        Schema::create('grade_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->uuid('academic_term_id')->nullable();
            $table->uuid('subject_id');
            $table->decimal('value', 6, 2);
            $table->decimal('max_value', 6, 2)->default(20);
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->date('assessed_on');
            $table->foreignUuid('recorded_by_person_id')->constrained('persons');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'enrollment_id']);
        });

        DB::statement('ALTER TABLE grade_entries ADD CONSTRAINT grade_entries_enroll_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement('ALTER TABLE grade_entries ADD CONSTRAINT grade_entries_subject_fk FOREIGN KEY (school_id, subject_id) REFERENCES subjects (school_id, id)');
        DB::statement('ALTER TABLE grade_entries ADD CONSTRAINT grade_entries_term_fk FOREIGN KEY (school_id, academic_term_id) REFERENCES academic_terms (school_id, id)');

        Schema::create('student_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->string('category');
            $table->string('severity')->default('attention');
            $table->string('reason_summary');
            $table->timestampTz('detected_at');
            $table->string('detector_version')->default('1');
            $table->string('recommended_action')->nullable();
            $table->string('status')->default('open');
            $table->foreignUuid('acknowledged_by_person_id')->nullable()->constrained('persons');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status']);
        });

        DB::statement('ALTER TABLE student_alerts ADD CONSTRAINT student_alerts_enroll_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');

        Schema::create('student_alert_signals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('alert_id');
            $table->string('signal_type');
            $table->decimal('observed_value', 10, 2)->nullable();
            $table->decimal('baseline_value', 10, 2)->nullable();
            $table->date('window_start')->nullable();
            $table->date('window_end')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
        });

        DB::statement('ALTER TABLE student_alert_signals ADD CONSTRAINT student_alert_signals_alert_fk FOREIGN KEY (school_id, alert_id) REFERENCES student_alerts (school_id, id)');

        foreach (self::TENANT_TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TENANT_TABLES) as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            Schema::dropIfExists($table);
        }

        Schema::dropIfExists('document_verification_events');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('supersedes_document_id');
        });
    }
};
