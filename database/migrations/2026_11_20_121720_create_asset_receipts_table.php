<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_receipts', function (Blueprint $table) {
            $table->id();
            $table->integer('asset_id')->unique();
            $table->integer('received_by')->index();
            $table->string('serial');
            $table->string('previous_tag');
            $table->string('asset_tag')->unique();
            $table->string('condition', 30);
            $table->text('note')->nullable();
            $table->integer('status_id');
            $table->integer('location_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_receipts');
    }
};
