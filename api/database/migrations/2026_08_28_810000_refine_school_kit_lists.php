<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kit_definitions', function (Blueprint $table): void {
            $table->string('price_source')->default('supplier');
            $table->uuid('copied_from_id')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX kit_definitions_year_grade_unique ON kit_definitions (school_id, school_year_id, grade_level_id) WHERE grade_level_id IS NOT NULL');
        DB::statement('ALTER TABLE kit_definitions ADD CONSTRAINT kit_definitions_copied_fk FOREIGN KEY (school_id, copied_from_id) REFERENCES kit_definitions (school_id, id)');

        Schema::table('kit_pack_items', function (Blueprint $table): void {
            $table->string('brand')->nullable();
        });

        DB::statement('ALTER TABLE kit_pack_items ADD CONSTRAINT kit_pack_items_need_fk FOREIGN KEY (school_id, need_id) REFERENCES kit_needs (school_id, id)');

        Schema::table('kit_orders', function (Blueprint $table): void {
            $table->uuid('kit_definition_id')->nullable();
            $table->string('fulfillment')->default('partner');
        });

        DB::statement('ALTER TABLE kit_orders ALTER COLUMN kit_pack_id DROP NOT NULL');
        DB::statement('ALTER TABLE kit_orders ALTER COLUMN supplier_id DROP NOT NULL');
        DB::statement('ALTER TABLE kit_orders DROP CONSTRAINT IF EXISTS kit_orders_amount_chk');
        DB::statement(<<<'SQL'
ALTER TABLE kit_orders ADD CONSTRAINT kit_orders_amount_chk CHECK (
    (fulfillment = 'partner' AND total_amount > 0 AND kit_pack_id IS NOT NULL)
    OR (fulfillment = 'self' AND total_amount = 0 AND kit_pack_id IS NULL)
)
SQL);
        DB::statement('ALTER TABLE kit_orders ADD CONSTRAINT kit_orders_def_fk FOREIGN KEY (school_id, kit_definition_id) REFERENCES kit_definitions (school_id, id)');
        DB::statement('UPDATE kit_orders o SET kit_definition_id = p.kit_definition_id FROM kit_packs p WHERE o.kit_pack_id = p.id AND o.kit_definition_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kit_orders DROP CONSTRAINT IF EXISTS kit_orders_def_fk');
        DB::statement('ALTER TABLE kit_orders DROP CONSTRAINT IF EXISTS kit_orders_amount_chk');
        DB::statement('ALTER TABLE kit_orders ADD CONSTRAINT kit_orders_amount_chk CHECK (total_amount > 0)');
        DB::statement('ALTER TABLE kit_orders ALTER COLUMN kit_pack_id SET NOT NULL');
        DB::statement('ALTER TABLE kit_orders ALTER COLUMN supplier_id SET NOT NULL');

        Schema::table('kit_orders', function (Blueprint $table): void {
            $table->dropColumn(['kit_definition_id', 'fulfillment']);
        });

        DB::statement('ALTER TABLE kit_pack_items DROP CONSTRAINT IF EXISTS kit_pack_items_need_fk');

        Schema::table('kit_pack_items', function (Blueprint $table): void {
            $table->dropColumn('brand');
        });

        DB::statement('ALTER TABLE kit_definitions DROP CONSTRAINT IF EXISTS kit_definitions_copied_fk');
        DB::statement('DROP INDEX IF EXISTS kit_definitions_year_grade_unique');

        Schema::table('kit_definitions', function (Blueprint $table): void {
            $table->dropColumn(['price_source', 'copied_from_id']);
        });
    }
};
