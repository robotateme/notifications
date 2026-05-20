<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_messages', function (Blueprint $table) {
            $table->string('subscriber_id')->nullable()->after('idempotency_key')->index();
            $table->string('priority', 32)->default('marketing')->after('channel')->index();
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('dropped_at')->nullable()->after('delivered_at');
        });

        DB::table('notification_messages')
            ->whereNull('subscriber_id')
            ->update(['subscriber_id' => DB::raw('recipient')]);

        Schema::table('notification_messages', function (Blueprint $table) {
            $table->index(['subscriber_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_messages', function (Blueprint $table) {
            $table->dropIndex(['subscriber_id', 'created_at']);
            $table->dropColumn([
                'subscriber_id',
                'priority',
                'delivered_at',
                'dropped_at',
            ]);
        });
    }
};
