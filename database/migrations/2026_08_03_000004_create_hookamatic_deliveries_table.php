<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('hookamatic_deliveries', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('outbound_event_id')->unsigned();
            $table->foreign('outbound_event_id')
                ->references('id')
                ->on('hookamatic_outbound_events')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->bigInteger('subscriber_id')->unsigned();
            $table->foreign('subscriber_id')
                ->references('id')
                ->on('hookamatic_subscribers')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            // Shared across every attempt row for the same event+subscriber pair,
            // so the subscriber can dedupe retries of what is logically one delivery.
            $table->uuid('delivery_uuid');
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status', 16);
            $table->unsignedTinyInteger('priority')->default(3);
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status_code')->nullable();
            $table->text('response_body')->nullable();
            // Populated when no HTTP response came back at all (connection failure,
            // missing subscriber, etc.) — response_status_code/response_body stay null.
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->bigInteger('retried_from_delivery_id')->unsigned()->nullable();
            $table->foreign('retried_from_delivery_id')
                ->references('id')
                ->on('hookamatic_deliveries')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->index(['outbound_event_id', 'subscriber_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hookamatic_deliveries');
    }

};
