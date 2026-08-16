<?php

namespace VanDmade\Hookamatic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use VanDmade\Hookamatic\Events\HookamaticLog;
use VanDmade\Hookamatic\Http\Requests\SubscriberRequest;
use VanDmade\Hookamatic\Http\Requests\TableRequest;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use VanDmade\Hookamatic\Services\SubscriberService;
use Exception;

class SubscriberController extends HookamaticController
{

    public function __construct(
        protected SubscriberService $subscriberService
    ) {}

    public function get(Subscriber $subscriber): JSONResponse
    {
        try {
            return $this->success([
                'subscriber' => $subscriber->load('eventTypes'),
            ]);
        } catch (Exception $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

    public function data(TableRequest $request): JSONResponse
    {
        try {
            $data = $request->validated();
            $subscribers = $this->subscriberService
                ->search($data)
                ->paginate($data['per_page'], ['*'], 'page', $data['page']);
            return $this->success([
                'total' => $subscribers->total(),
                'per_page' => $subscribers->perPage(),
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
                'data' => $subscribers->items(),
            ]);
        } catch (Exception $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }


    public function store(SubscriberRequest $request): JSONResponse
    {
        try {
            $data = $request->validated();
            $eventTypes = Arr::pull($data, 'event_types');
            $subscriber = $this->subscriberService->create($data);
            if (!is_null($eventTypes)) {
                $this->subscriberService->syncEventTypes($subscriber, $eventTypes);
            }
            return $this->success([
                'message' => __('hookamatic::subscriber.messages.created'),
                'subscriber' => $subscriber->load('eventTypes'),
            ], 201);
        } catch (Exception $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

    public function update(SubscriberRequest $request, Subscriber $subscriber): JSONResponse
    {
        try {
            $data = $request->validated();
            $eventTypes = Arr::pull($data, 'event_types');
            $subscriber = $this->subscriberService->update($subscriber, $data);
            if (!is_null($eventTypes)) {
                $this->subscriberService->syncEventTypes($subscriber, $eventTypes);
            }
            return $this->success([
                'message' => __('hookamatic::subscriber.messages.updated'),
                'subscriber' => $subscriber->load('eventTypes'),
            ]);
        } catch (Exception $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

    public function destroy(Subscriber $subscriber): JSONResponse
    {
        try {
            $this->subscriberService->delete($subscriber);
            return $this->success([
                'message' => __('hookamatic::subscriber.messages.deleted'),
            ], 204);
        } catch (Exception $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

    public function list(): JSONResponse
    {
        try {
            return $this->success([
                'list' => $this->subscriberService->search(['select' => ['id as value', 'name as label']])->get(),
            ]);
        } catch (Exception $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

}
