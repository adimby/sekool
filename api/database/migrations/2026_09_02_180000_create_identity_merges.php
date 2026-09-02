<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_merges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools');
            $table->foreignUuid('surviving_person_id')->constrained('persons');
            $table->foreignUuid('duplicate_person_id')->constrained('persons');
            $table->text('reason');
            $table->uuid('requested_by_person_id');
            $table->string('status', 32);
            $table->uuid('decided_by_person_id')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        RowLevelSecurity::enable('identity_merges', 'school_id', allowPlatformAdmin: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_merges');
    }
};
