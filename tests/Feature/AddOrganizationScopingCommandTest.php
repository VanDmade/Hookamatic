<?php

namespace VanDmade\Hookamatic\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use VanDmade\Hookamatic\Tests\TestCase;
use stdClass;

class AddOrganizationScopingCommandTest extends TestCase
{

    public function test_fails_when_no_organization_model_is_configured(): void
    {
        config(['hookamatic.organization_model' => null]);
        $this->artisan('hookamatic:add-organization-scoping')
            ->assertExitCode(1);
    }

    public function test_fails_when_the_organization_model_is_not_an_eloquent_model(): void
    {
        config(['hookamatic.organization_model' => stdClass::class]);
        $this->artisan('hookamatic:add-organization-scoping')
            ->assertExitCode(1);
    }

    public function test_adds_the_configured_column_to_every_configured_table(): void
    {
        config(['hookamatic.organization_model' => config('auth.providers.users.model')]);
        config(['hookamatic.tables' => [
            'hookamatic_subscribers' => 'organization_id',
            'hookamatic_event_types' => 'organization_id',
        ]]);
        $this->artisan('hookamatic:add-organization-scoping')
            ->assertExitCode(0);
        $this->assertTrue(Schema::hasColumn('hookamatic_subscribers', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('hookamatic_event_types', 'organization_id'));
    }

    public function test_safe_to_run_more_than_once(): void
    {
        config(['hookamatic.organization_model' => config('auth.providers.users.model')]);
        config(['hookamatic.tables' => [
            'hookamatic_subscribers' => 'organization_id',
        ]]);
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('hookamatic:add-organization-scoping')
                ->assertExitCode(0);
        }
        $this->assertTrue(Schema::hasColumn('hookamatic_subscribers', 'organization_id'));
    }

}
