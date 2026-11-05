<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->decimal('rate_per_100', 8, 4);
            $table->string('homestead_exemption_type')->default('none');
            $table->decimal('homestead_exemption_amount', 14, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisdictions');
    }
};
