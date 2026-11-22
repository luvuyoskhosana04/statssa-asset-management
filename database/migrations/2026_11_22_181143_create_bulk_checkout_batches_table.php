<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_checkout_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->integer('created_by')->index();
            $table->string('request_hash', 64);
            $table->string('mode', 20);
            $table->string('target_type');
            $table->integer('target_id')->index();
            $table->string('target_name');
            $table->integer('project_id')->nullable()->index();
            $table->integer('source_location_id')->nullable();
            $table->json('source');
            $table->json('items');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_checkout_batches');
    }
};
