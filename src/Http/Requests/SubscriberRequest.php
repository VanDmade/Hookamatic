<?php

namespace VanDmade\Hookamatic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use VanDmade\Hookamatic\Enums\Priority;

class SubscriberRequest extends FormRequest
{

    public function messages(): array
    {
        return [
            'name.string' => __('hookamatic::subscriber.name.string'),
            'name.max' => __('hookamatic::subscriber.name.max'),
            'slug.string' => __('hookamatic::subscriber.slug.string'),
            'slug.max' => __('hookamatic::subscriber.slug.max'),
            'slug.alpha_dash' => __('hookamatic::subscriber.slug.alpha_dash'),
            'slug.unique' => __('hookamatic::subscriber.slug.unique'),
            'url.required' => __('hookamatic::subscriber.url.required'),
            'url.url' => __('hookamatic::subscriber.url.url'),
            'url.max' => __('hookamatic::subscriber.url.max'),
            'headers.array' => __('hookamatic::subscriber.headers.array'),
            'api_key_reference.string' => __('hookamatic::subscriber.api_key_reference.string'),
            'api_key_reference.max' => __('hookamatic::subscriber.api_key_reference.max'),
            'rate_limit_max.integer' => __('hookamatic::subscriber.rate_limit_max.integer'),
            'rate_limit_max.min' => __('hookamatic::subscriber.rate_limit_max.min'),
            'rate_limit_interval_seconds.integer' => __('hookamatic::subscriber.rate_limit_interval_seconds.integer'),
            'rate_limit_interval_seconds.min' => __('hookamatic::subscriber.rate_limit_interval_seconds.min'),
            'organization_id.integer' => __('hookamatic::subscriber.organization_id.integer'),
            'expires_at.date' => __('hookamatic::subscriber.expires_at.date'),
            'event_types.array' => __('hookamatic::subscriber.event_types.array'),
            'event_types.*.event_type_id.required' => __('hookamatic::subscriber.event_types.event_type_id.required'),
            'event_types.*.event_type_id.integer' => __('hookamatic::subscriber.event_types.event_type_id.integer'),
            'event_types.*.event_type_id.exists' => __('hookamatic::subscriber.event_types.event_type_id.exists'),
            'event_types.*.priority.in' => __('hookamatic::subscriber.event_types.priority.in'),
            'event_types.*.response_protocol_reference.string' => __('hookamatic::subscriber.event_types.response_protocol_reference.string'),
            'event_types.*.response_protocol_reference.max' => __('hookamatic::subscriber.event_types.response_protocol_reference.max'),
        ];
    }

    public function rules(): array
    {
        // Populated for update (route-bound Subscriber), null on create.
        $subscriber = $this->route('subscriber');

        return [
            'name' => 'nullable|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('hookamatic_subscribers', 'slug')->ignore($subscriber?->id),
            ],
            'url' => 'required|url|max:2048',
            'headers' => 'nullable|array',
            'api_key_reference' => 'nullable|string|max:255',
            'rate_limit_max' => 'nullable|integer|min:1',
            'rate_limit_interval_seconds' => 'nullable|integer|min:1',
            'organization_id' => 'nullable|integer',
            'expires_at' => 'nullable|date',
            // The event types this subscriber should receive deliveries for.
            'event_types' => 'nullable|array',
            'event_types.*.event_type_id' => 'required|integer|exists:hookamatic_event_types,id',
            'event_types.*.priority' => ['nullable', Rule::in(array_column(Priority::cases(), 'name'))],
            'event_types.*.response_protocol_reference' => 'nullable|string|max:255',
        ];
    }

}
