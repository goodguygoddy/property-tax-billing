<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_runs', function (Blueprint $table) {
            $table->id();
            $table->date('as_of');
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('adjustment_count')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('client_id');
            $table->foreignId('billing_run_id')->constrained();
            $table->date('issued_on');
            $table->bigInteger('total_cents');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            // A property is placed on an invoice exactly once; later changes are adjustments.
            $table->string('property_id')->unique();
            $table->foreignId('outcome_event_id')->constrained();
            $this->feeComputationColumns($table);
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties');
        });

        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('client_id');
            $table->foreignId('billing_run_id')->constrained();
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('invoice_line_id')->constrained();
            $table->string('property_id');
            $table->foreignId('outcome_event_id')->constrained();
            $table->date('issued_on');
            $table->bigInteger('previous_fee_cents');
            $table->bigInteger('amount_cents');
            $this->feeComputationColumns($table);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients');
            $table->foreign('property_id')->references('id')->on('properties');
        });
    }

    /**
     * Columns that record how a property's fee was arrived at, so a charge
     * can be explained later even if rates or rules change.
     */
    private function feeComputationColumns(Blueprint $table): void
    {
        $table->decimal('noticed_value', 16, 2);
        $table->decimal('final_value', 16, 2);
        $table->decimal('savings', 24, 8);
        $table->decimal('fee_rate', 6, 4);
        $table->decimal('raw_fee', 24, 8);
        $table->bigInteger('fee_cents');
        $table->boolean('waived')->default(false);
        $table->boolean('capped')->default(false);
        $table->json('tax_breakdown');
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('billing_runs');
    }
};
