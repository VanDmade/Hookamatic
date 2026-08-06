<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $userModel = new (config('auth.providers.users.model'))();
        Schema::create('hookamatic_outbound_events', function (Blueprint $table) use ($userModel) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('event_type_id')->unsigned();
            $table->foreign('event_type_id')
                ->references('id')
                ->on('hookamatic_event_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->json('payload');
            $table->string('caller_file')->nullable();
            $table->string('caller_class')->nullable();
            $table->string('caller_method')->nullable();
            $table->unsignedInteger('caller_line')->nullable();
            $table->index(['event_type_id', 'created_at']);
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hookamatic_outbound_events');
    }

};
