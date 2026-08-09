<?php

return [
    'name' => [
        'required' => 'A name is required.',
        'string' => 'The name must be a string.',
        'max' => 'The name may not be greater than 255 characters.',
        'unique' => 'That name is already in use by another event type.',
    ],
    'description' => [
        'string' => 'The description must be a string.',
    ],
    'errors' => [
        'update_failed' => 'Failed to update the event type.',
    ],
    'messages' => [
        'created' => 'Event type created successfully.',
        'updated' => 'Event type updated successfully.',
        'deleted' => 'Event type deleted successfully.',
    ],
];
