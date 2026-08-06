<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('hookamatic_inbound_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('provider');
            $table->string('provider_event_id')->nullable();
            // The provider's own event type name (e.g. charge.succeeded) - a separate,
            // uncontrolled vocabulary, not our own hookamatic_event_types catalog.
            $table->string('event_type')->nullable();
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->ipAddress('ip_address')->nullable();
            $table->string('status', 16);
            // Incremented in place (not a new row) each time the provider retries an
            // already-seen provider_event_id. Starts at 0 since the middleware
            // unconditionally increments it once per call, including the first.
            $table->unsignedInteger('attempt_counter')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['provider', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hookamatic_inbound_events');
    }

};
