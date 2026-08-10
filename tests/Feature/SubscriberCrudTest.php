<?php

namespace VanDmade\Hookamatic\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\Subscribers\Event;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use VanDmade\Hookamatic\Tests\TestCase;

class SubscriberCrudTest extends TestCase
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

    private function makeSubscriber(array $overrides = []): Subscriber
    {
        return Subscriber::create(array_merge([
            'url' => 'https://example.test/webhooks',
        ], $overrides));
    }

    public function test_guests_cannot_access_any_subscriber_route(): void
    {
        $subscriber = $this->makeSubscriber();
        $this->getJson('/hookamatic/subscriber/data')->assertForbidden();
        $this->getJson('/hookamatic/list/subscribers')->assertForbidden();
        $this->postJson('/hookamatic/subscriber', ['url' => 'https://x.test'])->assertForbidden();
        $this->getJson('/hookamatic/subscriber/'.$subscriber->id)->assertForbidden();
        $this->putJson('/hookamatic/subscriber/'.$subscriber->id, ['url' => 'https://x.test'])->assertForbidden();
        $this->deleteJson('/hookamatic/subscriber/'.$subscriber->id)->assertForbidden();
    }

    public function test_lists_subscribers_with_pagination(): void
    {
        $this->actingAsUser();
        $this->makeSubscriber();
        $this->makeSubscriber();
        $this->makeSubscriber();
        $response = $this->getJson('/hookamatic/subscriber/data');
        $response->assertOk();
        $response->assertJson(['total' => 3]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_searches_subscribers_by_name(): void
    {
        $this->actingAsUser();
        $this->makeSubscriber(['name' => 'Acme Inc']);
        $this->makeSubscriber(['name' => 'Widgets Co']);
        $response = $this->getJson('/hookamatic/subscriber/data?search=Acme');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Acme Inc', $response->json('data.0.name'));
    }

    public function test_shows_a_subscriber_by_id_or_slug(): void
    {
        $this->actingAsUser();
        $subscriber = $this->makeSubscriber(['name' => 'Acme Inc']);
        $byId = $this->getJson('/hookamatic/subscriber/'.$subscriber->id);
        $bySlug = $this->getJson('/hookamatic/subscriber/'.$subscriber->slug);
        $byId->assertOk();
        $byId->assertJson(['subscriber' => ['id' => $subscriber->id]]);
        $bySlug->assertOk();
        $bySlug->assertJson(['subscriber' => ['id' => $subscriber->id]]);
    }

    public function test_show_returns_404_for_an_unknown_subscriber(): void
    {
        $this->actingAsUser();
        $this->getJson('/hookamatic/subscriber/no-such-subscriber')->assertNotFound();
    }

    public function test_subscriber_response_never_exposes_the_signing_secret(): void
    {
        $this->actingAsUser();
        $subscriber = $this->makeSubscriber();
        $response = $this->getJson('/hookamatic/subscriber/'.$subscriber->id);
        $response->assertJsonMissingPath('subscriber.signing_secret');
    }

    public function test_returns_a_lightweight_list_for_dropdowns(): void
    {
        $this->actingAsUser();
        $this->makeSubscriber(['name' => 'Acme Inc']);
        $response = $this->getJson('/hookamatic/list/subscribers');
        $response->assertOk();
        $response->assertJsonStructure(['list' => [['value', 'label']]]);
    }

    public function test_creates_a_subscriber(): void
    {
        $this->actingAsUser();
        $response = $this->postJson('/hookamatic/subscriber', [
            'name' => 'Acme Inc',
            'url' => 'https://acme.test/webhooks',
        ]);
        $response->assertCreated();
        $this->assertDatabaseHas('hookamatic_subscribers', [
            'name' => 'Acme Inc',
            'url' => 'https://acme.test/webhooks',
        ]);
    }

    public function test_store_requires_a_url(): void
    {
        $this->actingAsUser();
        $response = $this->postJson('/hookamatic/subscriber', ['name' => 'Acme Inc']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url');
    }

    public function test_creates_a_subscriber_with_event_type_subscriptions(): void
    {
        $this->actingAsUser();
        $eventType = EventType::create(['name' => 'order.shipped']);
        $response = $this->postJson('/hookamatic/subscriber', [
            'url' => 'https://acme.test/webhooks',
            'event_types' => [
                ['event_type_id' => $eventType->id, 'priority' => 'HIGH'],
            ],
        ]);
        $response->assertCreated();
        $subscriberId = $response->json('subscriber.id');
        $this->assertDatabaseHas('hookamatic_subscriber_events', [
            'subscriber_id' => $subscriberId,
            'event_type_id' => $eventType->id,
            'priority' => 4, // Priority::HIGH
        ]);
    }

    public function test_updates_a_subscriber(): void
    {
        $this->actingAsUser();
        $subscriber = $this->makeSubscriber(['name' => 'Old Name']);
        $response = $this->putJson('/hookamatic/subscriber/'.$subscriber->id, [
            'name' => 'New Name',
            'url' => $subscriber->url,
        ]);
        $response->assertOk();
        $this->assertSame('New Name', $subscriber->fresh()->name);
    }

    public function test_updates_event_type_subscriptions_removes_ones_not_in_the_list(): void
    {
        $this->actingAsUser();
        $subscriber = $this->makeSubscriber();
        $eventTypeOne = EventType::create(['name' => 'order.shipped']);
        $eventTypeTwo = EventType::create(['name' => 'order.cancelled']);
        Event::create(['subscriber_id' => $subscriber->id, 'event_type_id' => $eventTypeOne->id]);
        $this->putJson('/hookamatic/subscriber/'.$subscriber->id, [
            'url' => $subscriber->url,
            'event_types' => [
                ['event_type_id' => $eventTypeTwo->id],
            ],
        ])->assertOk();
        $this->assertDatabaseMissing('hookamatic_subscriber_events', [
            'subscriber_id' => $subscriber->id,
            'event_type_id' => $eventTypeOne->id,
        ]);
        $this->assertDatabaseHas('hookamatic_subscriber_events', [
            'subscriber_id' => $subscriber->id,
            'event_type_id' => $eventTypeTwo->id,
        ]);
    }

    public function test_deletes_a_subscriber(): void
    {
        $this->actingAsUser();
        $subscriber = $this->makeSubscriber();
        $response = $this->deleteJson('/hookamatic/subscriber/'.$subscriber->id);
        $response->assertStatus(204);
        $this->assertDatabaseMissing('hookamatic_subscribers', ['id' => $subscriber->id]);
    }

}
