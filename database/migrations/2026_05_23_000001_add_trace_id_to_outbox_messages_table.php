<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('outbox_messages', 'trace_id')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->string('trace_id', 128)->nullable()->after('aggregate_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('outbox_messages', 'trace_id')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->dropColumn('trace_id');
        });
    }
};
