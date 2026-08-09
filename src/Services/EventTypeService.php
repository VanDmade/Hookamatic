<?php

namespace VanDmade\Hookamatic\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use RuntimeException;

class EventTypeService
{

    private const SORTABLE_COLUMNS = ['id', 'name', 'created_at', 'updated_at'];
    private const SEARCHABLE_COLUMNS = ['name', 'description'];

    public function get($id): ?EventType
    {
        return EventType::find($id);
    }

    public function getByName(string $name): ?EventType
    {
        return EventType::where('name', '=', $name)->first();
    }

    public function create(array $data): EventType
    {
        return EventType::create($data);
    }

    public function update(EventType $eventType, array $data): EventType
    {
        $eventType->fill($data);
        if (!$eventType->save()) {
            throw new RuntimeException(__('hookamatic::event_type.errors.update_failed'));
        }
        return $eventType;
    }

    public function delete(EventType $eventType): bool
    {
        // Deletes all event links associated with this event type
        $eventType->subscriberLinks()->delete();
        return $eventType->delete();
    }

    public function search(array $options, $defaultSort = 'created_at'): Builder
    {
        $defaultSort = config('hookamatic.event_type.default_sort_column', $defaultSort);
        $defaultOrder = config('hookamatic.event_type.default_sort_order', 'asc');
        $validSortBy = in_array($options['sort_by'] ?? null, self::SORTABLE_COLUMNS, true);
        $sortBy = $validSortBy ? $options['sort_by'] : $defaultSort;
        $sortOrder = ($options['sort_order'] ?? $defaultOrder) === 'desc' ? 'desc' : 'asc';
        return EventType::select($options['select'] ?? ['*'])
            ->withCount('subscribers')
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

}