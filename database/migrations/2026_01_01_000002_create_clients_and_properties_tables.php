<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('client_id');
            $table->boolean('homestead')->default(false);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients');
        });

        Schema::create('property_jurisdiction', function (Blueprint $table) {
            $table->string('property_id');
            $table->string('jurisdiction_code');

            $table->primary(['property_id', 'jurisdiction_code']);
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('jurisdiction_code')->references('code')->on('jurisdictions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_jurisdiction');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('clients');
    }
};
