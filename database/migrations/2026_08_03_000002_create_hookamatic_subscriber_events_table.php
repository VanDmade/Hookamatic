<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $userModel = new (config('auth.providers.users.model'))();
        Schema::create('hookamatic_subscriber_events', function (Blueprint $table) use ($userModel) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('subscriber_id')->unsigned();
            $table->foreign('subscriber_id')
                ->references('id')
                ->on('hookamatic_subscribers')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->bigInteger('event_type_id')->unsigned();
            $table->foreign('event_type_id')
                ->references('id')
                ->on('hookamatic_event_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            // Reference into config('hookamatic.outbound.response_protocols'), not a raw class
            // name - same reasoning as api_key_reference on subscribers. Null = nothing is
            // called when a delivery for this subscriber+event pairing resolves.
            $table->string('response_protocol_reference')->nullable();
            $table->unique(['subscriber_id', 'event_type_id']);
            $table->index('event_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hookamatic_subscriber_events');
    }

};
