<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('school_year_id');
            $table->string('document_type');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'school_year_id', 'document_type']);
        });

        DB::statement('ALTER TABLE numbering_sequences ADD CONSTRAINT numbering_sequences_year_fk FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)');

        Schema::create('fee_schedules', function (Blueprint $table) {
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

        DB::statement('ALTER TABLE fee_schedules ADD CONSTRAINT fee_schedules_year_fk FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)');
        DB::statement('ALTER TABLE fee_schedules ADD CONSTRAINT fee_schedules_grade_fk FOREIGN KEY (school_id, grade_level_id) REFERENCES grade_levels (school_id, id)');

        Schema::create('fee_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('fee_schedule_id');
            $table->string('code');
            $table->string('label');
            $table->bigInteger('amount');
            $table->date('due_on');
            $table->string('category')->default('tuition');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'fee_schedule_id', 'code']);
        });

        DB::statement('ALTER TABLE fee_items ADD CONSTRAINT fee_items_schedule_fk FOREIGN KEY (school_id, fee_schedule_id) REFERENCES fee_schedules (school_id, id)');
        DB::statement('ALTER TABLE fee_items ADD CONSTRAINT fee_items_amount_positive_chk CHECK (amount > 0)');

        Schema::create('payer_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('family_id')->constrained('families')->restrictOnDelete();
            $table->foreignUuid('responsible_person_id')->constrained('persons')->restrictOnDelete();
            $table->bigInteger('credit_balance_ariary')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'family_id', 'responsible_person_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('enrollment_id');
            $table->uuid('payer_account_id');
            $table->uuid('school_year_id');
            $table->string('number');
            $table->date('issued_on');
            $table->bigInteger('total_amount');
            $table->bigInteger('discount_amount')->default(0);
            $table->string('discount_reason')->nullable();
            $table->bigInteger('net_amount');
            $table->string('status')->default('issued');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'school_year_id', 'number']);
            $table->index(['school_id', 'status']);
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_enrollment_fk FOREIGN KEY (school_id, enrollment_id) REFERENCES enrollments (school_id, id)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_payer_fk FOREIGN KEY (school_id, payer_account_id) REFERENCES payer_accounts (school_id, id)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_year_fk FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_net_amount_chk CHECK (net_amount = total_amount - discount_amount)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_discount_reason_chk CHECK (discount_amount = 0 OR discount_reason IS NOT NULL)');
        DB::statement("CREATE UNIQUE INDEX invoices_one_per_enrollment_year ON invoices (school_id, enrollment_id, school_year_id) WHERE status <> 'cancelled'");

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('invoice_id');
            $table->uuid('fee_item_id')->nullable();
            $table->string('label');
            $table->bigInteger('amount');
            $table->bigInteger('discount_amount')->default(0);
            $table->string('discount_reason')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'invoice_id']);
        });

        DB::statement('ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_invoice_fk FOREIGN KEY (school_id, invoice_id) REFERENCES invoices (school_id, id)');
        DB::statement('ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_fee_item_fk FOREIGN KEY (school_id, fee_item_id) REFERENCES fee_items (school_id, id)');
        DB::statement('ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_discount_reason_chk CHECK (discount_amount = 0 OR discount_reason IS NOT NULL)');

        Schema::create('installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('invoice_id');
            $table->unsignedSmallInteger('sequence');
            $table->date('due_on');
            $table->bigInteger('amount');
            $table->bigInteger('paid_amount')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'invoice_id', 'sequence']);
            $table->index(['school_id', 'status', 'due_on']);
        });

        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_invoice_fk FOREIGN KEY (school_id, invoice_id) REFERENCES invoices (school_id, id)');
        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_paid_not_over_chk CHECK (paid_amount >= 0 AND paid_amount <= amount)');

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('payer_account_id');
            $table->bigInteger('amount');
            $table->string('method');
            $table->date('received_on');
            $table->string('reference')->nullable();
            $table->foreignUuid('recorded_by_person_id')->constrained('persons');
            $table->uuid('idempotency_key')->nullable();
            $table->uuid('reversed_by_payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('posted');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'received_on']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_payer_fk FOREIGN KEY (school_id, payer_account_id) REFERENCES payer_accounts (school_id, id)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive_chk CHECK (amount > 0)');
        DB::statement('CREATE UNIQUE INDEX payments_idempotency_unique ON payments (school_id, idempotency_key) WHERE idempotency_key IS NOT NULL');

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('payment_id');
            $table->uuid('installment_id');
            $table->bigInteger('amount');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'payment_id']);
        });

        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_payment_fk FOREIGN KEY (school_id, payment_id) REFERENCES payments (school_id, id)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_installment_fk FOREIGN KEY (school_id, installment_id) REFERENCES installments (school_id, id)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_positive_chk CHECK (amount > 0)');

        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('payment_id');
            $table->string('number');
            $table->timestampTz('issued_at');
            $table->foreignUuid('issued_by_person_id')->constrained('persons');
            $table->uuid('cancelled_by_receipt_id')->nullable();
            $table->string('status')->default('issued');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'number']);
            $table->unique(['school_id', 'payment_id']);
        });

        DB::statement('ALTER TABLE receipts ADD CONSTRAINT receipts_payment_fk FOREIGN KEY (school_id, payment_id) REFERENCES payments (school_id, id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payer_accounts');
        Schema::dropIfExists('fee_items');
        Schema::dropIfExists('fee_schedules');
        Schema::dropIfExists('numbering_sequences');
    }
};
