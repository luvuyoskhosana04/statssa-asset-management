<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_issues', function (Blueprint $table) {
            $table->date('expected_checkin')->nullable();
            $table->json('asset_snapshot')->nullable();
            $table->text('issue_note')->nullable();
            $table->timestamp('handover_declared_at')->nullable();
            $table->string('notification_status', 30)->default('not_sent');
        });
    }

    public function down(): void
    {
        Schema::table('project_issues', fn (Blueprint $table) => $table->dropColumn(['expected_checkin', 'asset_snapshot', 'issue_note', 'handover_declared_at', 'notification_status']));
    }
};
