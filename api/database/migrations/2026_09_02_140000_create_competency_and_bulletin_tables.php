<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'competency_domains',
        'competency_items',
        'competency_assessments',
        'bulletin_comments',
    ];

    public function up(): void
    {
        Schema::create('competency_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('stage');
            $table->string('code');
            $table->string('label');
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'stage', 'code']);
            $table->index(['school_id', 'stage', 'sequence']);
        });

        DB::statement("ALTER TABLE competency_domains ADD CONSTRAINT competency_domains_stage_chk CHECK (stage IN ('preschool', 'primary'))");

        Schema::create('competency_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('domain_id');
            $table->string('label');
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'domain_id', 'sequence']);
        });

        DB::statement('ALTER TABLE competency_items ADD CONSTRAINT competency_items_domain_fk FOREIGN KEY (school_id, domain_id) REFERENCES competency_domains (school_id, id)');

        Schema::create('competency_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->uuid('classroom_id');
            $table->uuid('competency_item_id');
            $table->uuid('academic_term_id')->nullable();
            $table->string('level');
            $table->text('comment')->nullable();
            $table->date('assessed_on');
            $table->foreignUuid('recorded_by_person_id')->constrained('persons')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'enrollment_id', 'competency_item_id'], 'competency_assessments_enrollment_item_uidx');
            $table->index(['school_id', 'classroom_id']);
        });

        DB::statement('ALTER TABLE competency_assessments ADD CONSTRAINT competency_assessments_class_fk FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)');
        DB::statement('ALTER TABLE competency_assessments ADD CONSTRAINT competency_assessments_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement('ALTER TABLE competency_assessments ADD CONSTRAINT competency_assessments_item_fk FOREIGN KEY (school_id, competency_item_id) REFERENCES competency_items (school_id, id)');
        DB::statement("ALTER TABLE competency_assessments ADD CONSTRAINT competency_assessments_level_chk CHECK (level IN ('not_yet', 'in_progress', 'acquired'))");

        Schema::create('bulletin_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->uuid('academic_term_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->text('body');
            $table->foreignUuid('recorded_by_person_id')->constrained('persons')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'enrollment_id']);
        });

        DB::statement('ALTER TABLE bulletin_comments ADD CONSTRAINT bulletin_comments_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement('ALTER TABLE bulletin_comments ADD CONSTRAINT bulletin_comments_subject_fk FOREIGN KEY (school_id, subject_id) REFERENCES subjects (school_id, id)');
        DB::statement('CREATE UNIQUE INDEX bulletin_comments_overall_uidx ON bulletin_comments (school_id, enrollment_id) WHERE subject_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX bulletin_comments_subject_uidx ON bulletin_comments (school_id, enrollment_id, subject_id) WHERE subject_id IS NOT NULL');

        foreach (self::TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
