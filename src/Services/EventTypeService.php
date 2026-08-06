<?php

namespace VanDmade\Hookamatic\Services;

use Illuminate\Database\Eloquent\Collection;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;

class EventTypeService
{

    public function get($id): ?EventType
    {
        return EventType::find($id);
    }

    public function getByName(string $name): ?EventType
    {
        return EventType::where('name', '=', $name)->first();
    }

    /**
     * @return Collection<int, Subscriber>
     */
    public function subscribers(EventType $eventType): Collection
    {
        return $eventType->subscribers()->get();
    }

}