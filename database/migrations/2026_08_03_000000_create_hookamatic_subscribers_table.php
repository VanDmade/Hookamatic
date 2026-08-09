<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $userModel = new (config('auth.providers.users.model'));
        // Only set if the developer has already configured hookamatic.organization_model
        // before running this migration. If they set it later instead, they use the
        // hookamatic:add-organization-scoping command rather than re-running this file.
        $organizationModel = config('hookamatic.organization_model', null);
        $organizationModel = is_null($organizationModel) || !class_exists($organizationModel) ? null : new ($organizationModel)();

        Schema::create('hookamatic_subscribers', function (Blueprint $table) use ($userModel, $organizationModel) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('name')->nullable();
            // Auto-generated from name (spaces -> underscores) if not set explicitly. Lets a
            // developer reference a subscriber by a readable identifier instead of just its ID.
            $table->string('slug')->nullable()->unique();
            $table->string('url');
            $table->json('headers')->nullable();
            // Encrypted casts on the model produce ciphertext far longer than a varchar(255), hence text().
            $table->text('signing_secret');
            // Reference into config('hookamatic.outbound.api_keys'), not the key itself - see docs/09-configuration.md.
            $table->string('api_key_reference')->nullable();
            $table->bigInteger('disabled_by')->unsigned()->nullable();
            $table->foreign('disabled_by')
                ->references($userModel->getKeyName())
                ->on($userModel->getTable())
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->timestamp('disabled_at')->nullable();
            $table->text('disabled_reason')->nullable();
            // True when a job/system process (e.g. a failure-count circuit breaker)
            // disabled this. disabled_by non-null means a user did it from the frontend.
            $table->boolean('disabled_by_system')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_called_at')->nullable();
            // Null on either means no self-imposed outbound rate limit for this subscriber.
            $table->unsignedInteger('rate_limit_max')->nullable();
            $table->unsignedInteger('rate_limit_interval_seconds')->nullable();
            if (!is_null($organizationModel)) {
                $table->bigInteger('organization_id')->unsigned()->nullable();
                $table->foreign('organization_id')
                    ->references($organizationModel->getKeyName())
                    ->on($organizationModel->getTable())
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            }

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hookamatic_subscribers');
    }

};
