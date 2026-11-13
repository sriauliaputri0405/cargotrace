<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 30)->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('carrier_id')->constrained();
            $table->foreignId('origin_id')->constrained('locations');
            $table->foreignId('destination_id')->constrained('locations');
            $table->string('cargo_type', 50);
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('volume_cbm', 10, 2)->nullable();
            $table->decimal('freight_cost', 14, 2)->nullable();
            $table->char('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('freight_cost_idr', 16, 2)->nullable();
            $table->dateTime('expected_departure_at');
            $table->dateTime('expected_arrival_at');
            $table->dateTime('estimated_arrival_at')->nullable();
            $table->string('status', 20)->default('CREATED')->index();
            $table->boolean('is_delayed')->default(false)->index();
            $table->unsignedInteger('delay_hours')->default(0);
            $table->string('delay_severity', 10)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};