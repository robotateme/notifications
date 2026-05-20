<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('topic');
            $table->string('event_name')->index();
            $table->string('aggregate_id')->index();
            $table->string('trace_id', 128)->nullable()->index();
            $table->json('payload');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
