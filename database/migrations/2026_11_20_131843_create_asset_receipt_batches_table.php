<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_receipt_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->char('request_hash', 64);
            $table->integer('received_by')->index();
            $table->string('receiver_name', 512);
            $table->string('order_number', 191)->nullable();
            $table->integer('status_id')->nullable();
            $table->string('status_name')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('location_name')->nullable();
            $table->timestamps();
        });
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->string('model_name')->nullable();
            $table->string('asset_name')->nullable();
            $table->string('status_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('order_number', 191)->nullable();
        });
        foreach (DB::table('asset_receipts')->orderBy('id')->lazyById(250) as $receipt) {
            $user = DB::table('users')->where('id', $receipt->received_by)->first();
            $batchId = DB::table('asset_receipt_batches')->insertGetId([
                'request_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'legacy:'.$receipt->id),
                'received_by' => $receipt->received_by,
                'receiver_name' => $user ? ($user->display_name ?: trim($user->first_name.' '.$user->last_name)) : (string) $receipt->received_by,
                'status_id' => $receipt->status_id, 'location_id' => $receipt->location_id,
                'created_at' => $receipt->created_at, 'updated_at' => $receipt->updated_at,
            ]);
            DB::table('asset_receipts')->where('id', $receipt->id)->update(['batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        Schema::table('asset_receipts', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'model_name', 'asset_name', 'status_name', 'location_name', 'order_number']);
        });
        Schema::dropIfExists('asset_receipt_batches');
    }
};
