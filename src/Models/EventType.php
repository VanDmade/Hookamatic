<?php

namespace VanDmade\Hookamatic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use VanDmade\Hookamatic\Models\Subscribers;

class EventType extends Model
{

    use SoftDeletes;

    protected $table = 'hookamatic_event_types';

    protected $fillable = [
        'name',
        'description',
        'deleted_at',
        'deleted_by',
        'created_by',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'deleted_by',
        'created_by',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function($eventType) {
            if (auth()->check()) {
                $eventType->created_by = auth()->id();
            }
        });
        static::deleting(function($eventType) {
            if (auth()->check()) {
                $eventType->deleted_by = auth()->id();
                $eventType->save();
            }
        });
    }

    /**
     * @return HasManyThrough<Subscribers\Subscriber, Subscribers\Event, $this>
     */
    public function subscribers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Subscribers\Subscriber::class,
            Subscribers\Event::class,
            'event_type_id',
            'subscriber_id'
        );
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'deleted_by');
    }

}
