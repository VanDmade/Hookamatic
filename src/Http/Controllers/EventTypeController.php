<?php

namespace VanDmade\Hookamatic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use VanDmade\Hookamatic\Http\Requests\EventTypeRequest;
use VanDmade\Hookamatic\Http\Requests\TableRequest;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Services\EventTypeService;
use Exception;

class EventTypeController extends HookamaticController
{

    public function __construct(
        protected EventTypeService $eventTypes
    ) {}

    public function get(EventType $eventType): JSONResponse
    {
        return $this->success([
            'event_type' => $eventType,
        ]);
    }

    public function data(TableRequest $request): JSONResponse
    {
        try {
            $data = $request->validated();
            $eventTypes = $this->eventTypes
                ->search($data)
                ->paginate($data['per_page'], ['*'], 'page', $data['page']);
            return $this->success([
                'total' => $eventTypes->total(),
                'per_page' => $eventTypes->perPage(),
                'current_page' => $eventTypes->currentPage(),
                'last_page' => $eventTypes->lastPage(),
                'data' => $eventTypes->items(),
            ]);
        } catch (Exception $error) {
            return $this->error($error->getMessage(), 500);
        }
    }

    public function store(EventTypeRequest $request): JSONResponse
    {
        try {
            $eventType = $this->eventTypes->create($request->validated());
            return $this->success([
                'message' => __('hookamatic::event_type.messages.created'),
                'event_type' => $eventType,
            ], 201);
        } catch (Exception $error) {
            return $this->error($error->getMessage(), 500);
        }
    }

    public function update(EventTypeRequest $request, EventType $eventType): JSONResponse
    {
        try {
            $eventType = $this->eventTypes->update($eventType, $request->validated());
            if (!$eventType) {
                return $this->error(__('hookamatic::event_type.errors.update_failed'), 500);
            }
            return $this->success([
                'message' => __('hookamatic::event_type.messages.updated'),
                'event_type' => $eventType,
            ]);
        } catch (Exception $error) {
            return $this->error($error->getMessage(), 500);
        }
    }

    public function destroy(EventType $eventType): JSONResponse
    {
        $this->eventTypes->delete($eventType);
        return $this->success([
            'message' => __('hookamatic::event_type.messages.deleted'),
        ], 204);
    }

    public function list(): JSONResponse
    {
        return $this->success([
            'list' => $this->eventTypes->search(['select' => ['id as value', 'name as label']])->get(),
        ]);
    }

}
