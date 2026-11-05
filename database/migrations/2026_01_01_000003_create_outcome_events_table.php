<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outcome_events', function (Blueprint $table) {
            $table->id();
            $table->string('property_id');
            $table->string('source_id')->nullable()->unique();
            $table->date('recorded_at');
            $table->string('status');
            $table->decimal('noticed_value', 16, 2);
            $table->decimal('final_value', 16, 2)->nullable();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties');
            $table->index(['property_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outcome_events');
    }
};
