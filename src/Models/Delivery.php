<?php

namespace VanDmade\Hookamatic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Http\Client\Response;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Enums\Priority;

class Delivery extends Model
{

    protected $table = 'hookamatic_deliveries';

    protected $fillable = [
        'outbound_event_id',
        'subscriber_id',
        'delivery_uuid',
        'attempt_number',
        'status',
        'priority',
        'request_payload',
        'response_status_code',
        'response_body',
        'failure_reason',
        'duration_ms',
        'sent_at',
        'failed_at',
        'next_attempt_at',
        // Not an actual column, but we want to be able to mass-assign it for convenience.
        'response',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'status' => DeliveryStatus::class,
        'priority' => Priority::class,
    ];

    protected $hidden = [
        'request_payload',
        'response_body',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function($model) {
            if (is_null($model->status)) {
                $model->status = DeliveryStatus::PENDING;
            }
        });
    }

    /**
     * @return Attribute<array<string, mixed>, ?Response>
     */
    public function response(): Attribute
    {
        return Attribute::make(
            set: function(?Response $response) {
                return [
                    'response_status_code' => $response?->status() ?? null,
                    'response_body' => $response?->body() ?? null,
                ];
            },
        );
    }

    public function requestPayload(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true),
            set: fn ($value) => json_encode($value),
        );
    }

    /**
     * @return BelongsTo<OutboundEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(OutboundEvent::class, 'outbound_event_id');
    }

    /**
     * @return HasOneThrough<EventType, OutboundEvent>
     */
    public function eventType(): HasOneThrough
    {
        return $this->hasOneThrough(
            EventType::class,
            OutboundEvent::class,
            'id',
            'id',
            'outbound_event_id',
            'event_type_id'
        );
    }

    /**
     * @return BelongsTo<Subscribers\Subscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscribers\Subscriber::class, 'subscriber_id');
    }

}
