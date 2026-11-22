<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_accessory_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('project_id')->unsigned()->index();
            $table->integer('accessory_id')->unsigned()->index();
            $table->integer('from_location_id')->unsigned()->nullable();
            $table->integer('qty')->unsigned();
            $table->string('source_type', 16)->nullable();
            $table->string('source_value', 191)->nullable();
            $table->string('reference', 40)->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_accessory_allocations');
    }
};
