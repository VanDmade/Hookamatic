<?php

namespace VanDmade\Hookamatic\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Tests\TestCase;

class EventTypeCrudTest extends TestCase
{

    private function actingAsUser(): User
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test-'.uniqid().'@example.test';
        $user->password = bcrypt('password');
        $user->save();
        $this->actingAs($user);
        return $user;
    }

    public function test_guests_cannot_access_any_event_type_route(): void
    {
        $eventType = EventType::create(['name' => 'order.shipped']);
        $this->getJson('/hookamatic/event-type/data')->assertForbidden();
        $this->getJson('/hookamatic/list/event-types')->assertForbidden();
        $this->postJson('/hookamatic/event-type', ['name' => 'x'])->assertForbidden();
        $this->getJson('/hookamatic/event-type/'.$eventType->id)->assertForbidden();
        $this->putJson('/hookamatic/event-type/'.$eventType->id, ['name' => 'x'])->assertForbidden();
        $this->deleteJson('/hookamatic/event-type/'.$eventType->id)->assertForbidden();
    }

    public function test_lists_event_types_with_pagination(): void
    {
        $this->actingAsUser();
        EventType::create(['name' => 'order.shipped']);
        EventType::create(['name' => 'order.cancelled']);
        $response = $this->getJson('/hookamatic/event-type/data');
        $response->assertOk();
        $response->assertJson(['total' => 2]);
    }

    public function test_searches_event_types_by_name(): void
    {
        $this->actingAsUser();
        EventType::create(['name' => 'order.shipped']);
        EventType::create(['name' => 'invoice.paid']);
        $response = $this->getJson('/hookamatic/event-type/data?search=order');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('order.shipped', $response->json('data.0.name'));
    }

    public function test_shows_an_event_type(): void
    {
        $this->actingAsUser();
        $eventType = EventType::create(['name' => 'order.shipped']);
        $response = $this->getJson('/hookamatic/event-type/'.$eventType->id);
        $response->assertOk();
        $response->assertJson([
            'event_type' => [
                'id' => $eventType->id,
                'name' => 'order.shipped',
            ],
        ]);
    }

    public function test_returns_a_lightweight_list_for_dropdowns(): void
    {
        $this->actingAsUser();
        EventType::create(['name' => 'order.shipped']);
        $response = $this->getJson('/hookamatic/list/event-types');
        $response->assertOk();
        $response->assertJsonStructure([
            'list' => [['value', 'label']],
        ]);
    }

    public function test_creates_an_event_type(): void
    {
        $this->actingAsUser();
        $response = $this->postJson('/hookamatic/event-type', [
            'name' => 'order.shipped',
        ]);
        $response->assertCreated();
        $this->assertDatabaseHas('hookamatic_event_types', [
            'name' => 'order.shipped',
        ]);
    }

    public function test_requires_a_name(): void
    {
        $this->actingAsUser();
        $response = $this->postJson('/hookamatic/event-type', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_rejects_a_duplicate_name(): void
    {
        $this->actingAsUser();
        EventType::create(['name' => 'order.shipped']);
        $response = $this->postJson('/hookamatic/event-type', [
            'name' => 'order.shipped',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_updates_an_event_type(): void
    {
        $this->actingAsUser();
        $eventType = EventType::create(['name' => 'order.shipped']);
        $response = $this->putJson('/hookamatic/event-type/'.$eventType->id, [
            'name' => 'order.delivered',
        ]);
        $response->assertOk();
        $this->assertSame('order.delivered', $eventType->fresh()->name);
    }

    public function test_updating_with_its_own_current_name_does_not_trigger_a_uniqueness_error(): void
    {
        $this->actingAsUser();
        $eventType = EventType::create(['name' => 'order.shipped']);
        $response = $this->putJson('/hookamatic/event-type/'.$eventType->id, [
            'name' => 'order.shipped',
            'description' => 'updated description',
        ]);
        $response->assertOk();
    }

    public function test_soft_deletes_an_event_type(): void
    {
        $this->actingAsUser();
        $eventType = EventType::create(['name' => 'order.shipped']);
        $response = $this->deleteJson('/hookamatic/event-type/'.$eventType->id);
        $response->assertStatus(204);
        $this->assertNull(EventType::find($eventType->id));
        $this->assertNotNull(EventType::withTrashed()->find($eventType->id));
        $this->assertNotNull(EventType::withTrashed()->find($eventType->id)->deleted_at);
    }

    public function test_a_soft_deleted_names_name_can_be_reused(): void
    {
        $this->actingAsUser();
        $eventType = EventType::create(['name' => 'order.shipped']);
        $eventType->delete();
        // Make sure the event type is not held even if deleted.
        $response = $this->postJson('/hookamatic/event-type', ['name' => 'order.shipped']);
        $response->assertCreated();
    }

}
