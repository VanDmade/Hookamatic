<?php

return [
    'name' => [
        'string' => 'The name must be a string.',
        'max' => 'The name may not be greater than 255 characters.',
    ],
    'slug' => [
        'string' => 'The slug must be a string.',
        'max' => 'The slug may not be greater than 255 characters.',
        'alpha_dash' => 'The slug may only contain letters, numbers, dashes, and underscores.',
        'unique' => 'That slug is already taken by another subscriber.',
    ],
    'url' => [
        'required' => 'A URL is required.',
        'url' => 'The URL must be a valid URL.',
        'max' => 'The URL may not be greater than 2048 characters.',
    ],
    'headers' => [
        'array' => 'The headers value must be an array.',
    ],
    'api_key_reference' => [
        'string' => 'The API key reference must be a string.',
        'max' => 'The API key reference may not be greater than 255 characters.',
    ],
    'rate_limit_max' => [
        'integer' => 'The rate limit max must be an integer.',
        'min' => 'The rate limit max must be at least 1.',
    ],
    'rate_limit_interval_seconds' => [
        'integer' => 'The rate limit interval must be an integer.',
        'min' => 'The rate limit interval must be at least 1 second.',
    ],
    'organization_id' => [
        'integer' => 'The organization id must be an integer.',
    ],
    'expires_at' => [
        'date' => 'The expires at value must be a valid date.',
    ],
    'event_types' => [
        'array' => 'The event types value must be an array.',
        'event_type_id' => [
            'required' => 'Each event type entry must include an event_type_id.',
            'integer' => 'The event_type_id must be an integer.',
            'exists' => 'One of the selected event types does not exist.',
        ],
        'priority' => [
            'in' => 'The priority must be one of: LOWEST, LOW, NORMAL, HIGH, HIGHEST.',
        ],
        'response_protocol_reference' => [
            'string' => 'The response protocol reference must be a string.',
            'max' => 'The response protocol reference may not be greater than 255 characters.',
        ],
    ],
    'errors' => [
        'unknown_priority' => 'Unknown priority level: :priority',
        'not_found' => 'No subscriber found for identifier: :identifier',
    ],
    'messages' => [
        'created' => 'Subscriber created successfully.',
        'updated' => 'Subscriber updated successfully.',
        'deleted' => 'Subscriber deleted successfully.',
    ],
];
