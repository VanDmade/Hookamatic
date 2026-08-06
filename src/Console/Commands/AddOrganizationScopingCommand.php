<?php

namespace VanDmade\Hookamatic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AddOrganizationScopingCommand extends Command
{

    protected $signature = 'hookamatic:add-organization-scoping';
    protected $description = 'Add organization scoping to the application.';

    public function handle(): int
    {
        $organizationModel = config('hookamatic.organization_model', null);
        // Makes sure the set the model in the config file
        if (is_null($organizationModel)) {
            $this->error('Organization model is not configured or does not exist. Please set hookamatic.organization_model in your configuration.');
            return self::FAILURE;
        }
        // Checks to make sure they defined the model correctly, that it exists, and that it's an Eloquent model
        if (!is_subclass_of($organizationModel, \Illuminate\Database\Eloquent\Model::class)) {
            $this->error('Organization model class '.$organizationModel.' does not exist or is not an Eloquent model. Please check your configuration.');
            return self::FAILURE;
        }
        // Checks that the base migrations have actually been run before altering the table
        if (!Schema::hasTable('hookamatic_subscribers')) {
            $this->error('hookamatic_subscribers table does not exist. Run the base Hookamatic migrations first.');
            return self::FAILURE;
        }
        // Checks to see if the organization ID column already exists
        if (Schema::hasColumn('hookamatic_subscribers', 'organization_id')) {
            $this->info('organization_id already exists on hookamatic_subscribers, skipping.');
            return self::SUCCESS;
        }
        $organizationModelInstance = new $organizationModel;
        Schema::table('hookamatic_subscribers', function(Blueprint $table) use ($organizationModelInstance) {
            $table->bigInteger('organization_id')->unsigned()->nullable();
            $table->foreign('organization_id')
                ->references($organizationModelInstance->getKeyName())
                ->on($organizationModelInstance->getTable())
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        $this->info('organization_id added to hookamatic_subscribers table.');
        return self::SUCCESS;
    }

}
