<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('sales_branch_id');
            $table->bigInteger('customer_id')->nullable();
            $table->string('fulfillment_method', 16);
            $table->bigInteger('pickup_branch_id')->nullable();
            $table->text('instructions')->nullable();
            $table->char('request_hash', 64);
            $table->timestampTz('expires_at');
            $table->timestampTz('due_at');
            $table->bigInteger('total_rupiah');
            $table->jsonb('pricing_snapshot');
            $table->timestampTz('consumed_at')->nullable();
            $table->index(['tenant_id', 'expires_at']);
            $table->foreign(['tenant_id', 'sales_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'pickup_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('quote_id');
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('sales_branch_id');
            $table->bigInteger('pickup_branch_id')->nullable();
            $table->bigInteger('accepted_by_membership_id');
            $table->string('order_number', 64);
            $table->string('operational_status', 32);
            $table->string('fulfillment_method', 16);
            $table->timestampTz('due_at_original');
            $table->timestampTz('due_at_current');
            $table->jsonb('customer_snapshot');
            $table->timestampTz('accepted_at');
            $table->unique(['tenant_id', 'quote_id']);
            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'sales_branch_id', 'operational_status', 'due_at_current']);
            $table->foreign(['tenant_id', 'quote_id'])->references(['tenant_id', 'id'])->on('quotes')->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'sales_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'pickup_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['tenant_id', 'accepted_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('order_lines', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->bigInteger('service_id');
            $table->string('unit', 16);
            $table->decimal('measured_quantity', 12, 3);
            $table->decimal('billable_quantity', 12, 3);
            $table->bigInteger('unit_price_rupiah');
            $table->bigInteger('line_total_rupiah');
            $table->jsonb('service_snapshot');
            $table->string('status', 24)->default('active');
            $table->index(['tenant_id', 'order_id']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'service_id'])->references(['tenant_id', 'id'])->on('services')->restrictOnDelete();
        });

        Schema::create('order_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('order_id');
            $table->string('event_type', 60);
            $table->bigInteger('actor_membership_id');
            $table->text('reason')->nullable();
            $table->jsonb('payload');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'order_id', 'created_at']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'actor_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('sales_branch_id');
            $table->string('invoice_number', 64);
            $table->string('document_status', 16);
            $table->char('currency', 3)->default('IDR');
            $table->bigInteger('original_total_rupiah');
            $table->timestampTz('issued_at');
            $table->timestampTz('due_at')->nullable();
            $table->unique(['tenant_id', 'order_id']);
            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'sales_branch_id', 'document_status']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'sales_branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->bigInteger('invoice_id');
            $table->bigInteger('order_line_id')->nullable();
            $table->string('kind', 24);
            $table->string('description', 200);
            $table->string('unit', 16)->nullable();
            $table->decimal('quantity', 12, 3)->nullable();
            $table->bigInteger('unit_price_rupiah')->nullable();
            $table->bigInteger('amount_rupiah');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'invoice_id']);
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_line_id'])->references(['tenant_id', 'id'])->on('order_lines')->restrictOnDelete();
        });

        Schema::create('bags', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->string('label_code', 80);
            $table->bigInteger('current_location_id')->nullable();
            $table->string('custody_status', 24);
            $table->bigInteger('active_transfer_line_id')->nullable();
            $table->string('seal_code', 80)->nullable();
            $table->unique(['tenant_id', 'label_code']);
            $table->index(['tenant_id', 'order_id']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'current_location_id'])->references(['tenant_id', 'id'])->on('locations')->restrictOnDelete();
        });

        Schema::create('bag_contents', function (Blueprint $table): void {
            $this->metadata($table);
            $table->bigInteger('order_id');
            $table->bigInteger('bag_id');
            $table->bigInteger('order_line_id');
            $table->decimal('physical_quantity', 12, 3);
            $table->decimal('handed_over_quantity', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unique(['tenant_id', 'order_id', 'bag_id', 'order_line_id']);
            $table->index(['tenant_id', 'bag_id']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'bag_id'])->references(['tenant_id', 'id'])->on('bags')->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_line_id'])->references(['tenant_id', 'id'])->on('order_lines')->restrictOnDelete();
        });

        Schema::create('number_counters', function (Blueprint $table): void {
            $table->bigInteger('tenant_id');
            $table->bigInteger('branch_id');
            $table->string('counter_kind', 24);
            $table->date('local_period');
            $table->bigInteger('last_value')->default(0);
            $table->primary(['tenant_id', 'branch_id', 'counter_kind', 'local_period']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership', 'order'))");

        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_total_check CHECK (total_rupiah >= 0), ADD CONSTRAINT quotes_expiry_check CHECK (expires_at > created_at), ADD CONSTRAINT quotes_fulfillment_check CHECK (fulfillment_method IN ('pickup', 'delivery'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (operational_status IN ('draft', 'accepted', 'in_progress', 'partially_ready', 'ready', 'partially_handed_over', 'completed', 'cancelled')), ADD CONSTRAINT orders_fulfillment_check CHECK (fulfillment_method IN ('pickup', 'delivery')), ADD CONSTRAINT orders_accepted_check CHECK (quote_id IS NOT NULL AND order_number IS NOT NULL AND accepted_at IS NOT NULL AND due_at_original IS NOT NULL AND due_at_current IS NOT NULL)");
        DB::statement("ALTER TABLE order_lines ADD CONSTRAINT order_lines_unit_check CHECK (unit IN ('kg', 'pcs', 'pair', 'meter', 'package')), ADD CONSTRAINT order_lines_quantity_check CHECK (measured_quantity > 0 AND billable_quantity > 0 AND billable_quantity >= measured_quantity), ADD CONSTRAINT order_lines_money_check CHECK (unit_price_rupiah >= 0 AND line_total_rupiah >= 0), ADD CONSTRAINT order_lines_status_check CHECK (status IN ('active', 'cancelled'))");
        DB::statement("ALTER TABLE order_events ADD CONSTRAINT order_events_type_check CHECK (event_type IN ('accepted', 'revised', 'rescheduled', 'cancelled'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (document_status IN ('draft', 'issued', 'void')), ADD CONSTRAINT invoices_currency_check CHECK (currency = 'IDR'), ADD CONSTRAINT invoices_total_check CHECK (original_total_rupiah >= 0)");
        DB::statement("ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_kind_check CHECK (kind IN ('service', 'discount', 'delivery', 'tax', 'rounding')), ADD CONSTRAINT invoice_lines_service_shape_check CHECK (kind <> 'service' OR (unit IS NOT NULL AND quantity IS NOT NULL AND unit_price_rupiah IS NOT NULL)), ADD CONSTRAINT invoice_lines_amount_check CHECK (kind <> 'discount' OR amount_rupiah <= 0)");
        DB::statement("ALTER TABLE bags ADD CONSTRAINT bags_custody_check CHECK (custody_status IN ('at_location', 'in_transit', 'handed_over', 'exception'))");
        DB::statement('ALTER TABLE bag_contents ADD CONSTRAINT bag_contents_quantity_check CHECK (physical_quantity > 0 AND handed_over_quantity >= 0 AND handed_over_quantity <= physical_quantity)');
        DB::statement("ALTER TABLE number_counters ADD CONSTRAINT number_counters_kind_check CHECK (counter_kind IN ('order', 'invoice')), ADD CONSTRAINT number_counters_value_check CHECK (last_value >= 0)");
        DB::statement('CREATE UNIQUE INDEX invoice_lines_one_service_per_order_line ON invoice_lines (tenant_id, order_line_id) WHERE order_line_id IS NOT NULL');

        foreach (['quotes', 'orders', 'order_lines', 'order_events', 'invoices', 'invoice_lines', 'bags', 'bag_contents', 'number_counters'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$name}_tenant_scope ON {$name} FOR ALL USING (tenant_id = app_current_tenant_id()) WITH CHECK (tenant_id = app_current_tenant_id())");
        }

        DB::statement('REVOKE DELETE ON quotes, orders, order_lines, invoices, bags, bag_contents FROM netclean_runtime');
        DB::statement('REVOKE UPDATE, DELETE ON order_events, invoice_lines FROM netclean_runtime');
        DB::statement('REVOKE DELETE ON number_counters FROM netclean_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE ON quotes, orders, order_lines, invoices, bags, bag_contents, number_counters TO netclean_runtime');
        DB::statement('GRANT SELECT, INSERT ON order_events, invoice_lines TO netclean_runtime');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE command_receipts DROP CONSTRAINT command_receipts_result_resource_check');
        DB::statement("ALTER TABLE command_receipts ADD CONSTRAINT command_receipts_result_resource_check CHECK (result_resource_type IS NULL OR result_resource_type IN ('tenant_membership'))");

        Schema::dropIfExists('number_counters');
        Schema::dropIfExists('bag_contents');
        Schema::dropIfExists('bags');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('quotes');
    }

    private function metadata(Blueprint $table): void
    {
        $table->id()->generatedAs();
        $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
        $table->timestampTz('created_at')->useCurrent();
        $table->timestampTz('updated_at')->useCurrent();
        $table->bigInteger('version')->default(1);
        $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};
