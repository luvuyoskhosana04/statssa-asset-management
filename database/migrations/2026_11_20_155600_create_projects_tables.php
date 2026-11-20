<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->string('status', 20)->default('planned')->index();
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->integer('company_id')->nullable()->index();
            $table->integer('location_id')->nullable()->index();
            $table->integer('reservation_status_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('project_user', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('project_id')->index();
            $table->integer('user_id')->index();
            $table->unique(['project_id', 'user_id']);
            $table->timestamps();
        });
        Schema::create('project_asset', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('project_id')->index();
            $table->integer('asset_id')->unique();
            $table->integer('original_status_id');
            $table->integer('reserved_by');
            $table->timestamps();
        });
        Schema::create('project_issue_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('project_id')->index();
            $table->integer('created_by')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_issues', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('session_id')->index();
            $table->integer('asset_id')->index();
            $table->integer('user_id')->index();
            $table->integer('acceptance_id')->nullable();
            $table->string('asset_tag');
            $table->string('recipient_name');
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('confirmed_by')->nullable();
            $table->timestamps();
        });
        Schema::create('project_events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('project_id')->index();
            $table->integer('actor_id');
            $table->string('action', 40);
            $table->string('description', 1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['project_events', 'project_issues', 'project_issue_sessions', 'project_asset', 'project_user', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
