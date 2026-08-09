<?php

namespace VanDmade\Hookamatic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventTypeRequest extends FormRequest
{

    public function messages(): array
    {
        return [
            'name.required' => __('hookamatic::event_type.name.required'),
            'name.string' => __('hookamatic::event_type.name.string'),
            'name.max' => __('hookamatic::event_type.name.max'),
            'name.unique' => __('hookamatic::event_type.name.unique'),
            'description.string' => __('hookamatic::event_type.description.string'),
        ];
    }

    public function rules(): array
    {
        // Populated for update (route-bound EventType), null on create.
        $eventType = $this->route('eventType');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('hookamatic_event_types', 'name')
                    ->ignore($eventType?->id)
                    ->where(fn($query) => $query->whereNull('deleted_at')),
            ],
            'description' => 'nullable|string',
        ];
    }

}
