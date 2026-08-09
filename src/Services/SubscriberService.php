<?php

namespace VanDmade\Hookamatic\Services;

use Illuminate\Database\Eloquent\Builder;
use VanDmade\Hookamatic\Enums\Priority;
use VanDmade\Hookamatic\Models\Subscribers\Event;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use InvalidArgumentException;

class SubscriberService
{

    private const SORTABLE_COLUMNS = [
        'id', 'name', 'slug', 'url', 'created_at', 'updated_at',
        'disabled_at', 'expires_at', 'last_called_at',
    ];
    private const SEARCHABLE_COLUMNS = ['name', 'slug', 'url'];

    public function get($id): ?Subscriber
    {
        return Subscriber::find($id);
    }

    public function getBySlug(string $slug): ?Subscriber
    {
        return Subscriber::where('slug', '=', $slug)->first();
    }

    public function findByIdOrSlug(int|string $identifier): Subscriber
    {
        $subscriber = is_numeric($identifier) ?
            $this->get((int)$identifier) : $this->getBySlug((string)$identifier);
        if (is_null($subscriber)) {
            throw new InvalidArgumentException(
                __('hookamatic::subscriber.errors.not_found', ['identifier' => $identifier])
            );
        }
        return $subscriber;
    }

    public function create(array $data): Subscriber
    {
        return Subscriber::create($data);
    }

    public function update(Subscriber $subscriber, array $data): Subscriber
    {
        $subscriber->fill($data);
        $subscriber->save();
        return $subscriber;
    }

    public function delete(Subscriber $subscriber): bool
    {
        // Deletes all events associated with this subscriber
        $subscriber->eventLinks()->delete();
        return $subscriber->delete();
    }

    public function syncEventTypes(Subscriber $subscriber, array $eventTypes): void
    {
        $eventTypeIds = array_column($eventTypes, 'event_type_id');
        // Removes all events for this subscriber that are not associated with the current event list
        $subscriber->eventLinks()
            ->whereNotIn('event_type_id', $eventTypeIds)
            ->delete();
        foreach ($eventTypes as $eventType) {
            Event::updateOrCreate(
                [ 'subscriber_id' => $subscriber->id, 'event_type_id' => $eventType['event_type_id']],
                [
                    'priority' => $this->resolvePriority($eventType['priority'] ?? null),
                    'response_protocol_reference' => $eventType['response_protocol_reference'] ?? null,
                ]
            );
        }
    }

    private function resolvePriority(?string $name): Priority
    {
        if (empty($name)) {
            // Returns the default priority if no name is provided... Unsure why someone wouldn't send one...
            return Priority::NORMAL;
        }
        foreach (Priority::cases() as $case) {
            if (strtolower($case->name) === strtolower($name)) {
                return $case;
            }
        }
        // Somehow they sent a priority that doesn't exist...
        throw new InvalidArgumentException(
            __('hookamatic::subscriber.errors.unknown_priority', ['priority' => $name])
        );
    }

    public function search(array $options): Builder
    {
        $defaultSort = config('hookamatic.subscriber.default_sort_column', 'created_at');
        $defaultOrder = config('hookamatic.subscriber.default_sort_order', 'asc');
        $validSortBy = in_array($options['sort_by'] ?? null, self::SORTABLE_COLUMNS, true);
        $sortBy = $validSortBy ? $options['sort_by'] : $defaultSort;
        $sortOrder = ($options['sort_order'] ?? $defaultOrder) === 'desc' ? 'desc' : 'asc';
        return Subscriber::select($options['select'] ?? ['*'])
            ->withCount('eventTypes')
            ->when(!empty($options['search']), function($query) use ($options) {
                $search = $options['search'];
                $query->where(function($query) use ($search) {
                    foreach (self::SEARCHABLE_COLUMNS as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy($sortBy, $sortOrder);
    }

    public function markAsDisabled(int|Subscriber $subscriber, string $reason): void
    {
        // Allows for the subscriber to be passed in as either an integer ID or a Subscriber instance
        if (is_int($subscriber)) {
            $subscriber = $this->get($subscriber);
        }
        if ($subscriber) {
            $subscriber->disabled_at = now();
            $subscriber->disabled_reason = $reason;
            if (auth()->check()) {
                $subscriber->disabled_by = auth()->id();
            } else {
                $subscriber->disabled_by_system = true;
            }
            $subscriber->save();
        }
    }

    public function markAsEnabled(int|Subscriber $subscriber): void
    {
        // Allows for the subscriber to be passed in as either an integer ID or a Subscriber instance
        if (is_int($subscriber)) {
            $subscriber = $this->get($subscriber);
        }
        if ($subscriber) {
            $subscriber->disabled_at = null;
            $subscriber->disabled_reason = null;
            $subscriber->disabled_by = null;
            $subscriber->disabled_by_system = false;
            $subscriber->save();
        }
    }

}