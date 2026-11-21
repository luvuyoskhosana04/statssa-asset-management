<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_receiving_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('user_id')->index();
            $table->json('payload');
            $table->unsignedInteger('item_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_receiving_drafts');
    }
};
