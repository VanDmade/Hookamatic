<?php

namespace VanDmade\Hookamatic\Models\Subscribers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VanDmade\Hookamatic\Models\EventType;

class Event extends Model
{

    protected $table = 'hookamatic_subscriber_events';

    protected $fillable = [
        'response_protocol_reference',
        'subscriber_id',
        'event_type_id',
        'created_by',
    ];

    protected $casts = [];

    protected $hidden = [
        'subscriber_id',
        'event_type_id',
        'created_by',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
    }

    /**
     * @return BelongsTo<Subscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    /**
     * @return BelongsTo<EventType, $this>
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'event_type_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

}
