<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $userModel = new (config('auth.providers.users.model'));
        Schema::create('hookamatic_event_types', function (Blueprint $table) use ($userModel) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->bigInteger('deleted_by')->unsigned()->nullable();
            $table->foreign('deleted_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hookamatic_event_types');
    }

};
