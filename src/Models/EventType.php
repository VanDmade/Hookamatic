<?php

namespace VanDmade\Hookamatic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use VanDmade\Hookamatic\Concerns\HasOrganization;
use VanDmade\Hookamatic\Models\Subscribers;

class EventType extends Model
{

    use SoftDeletes, HasOrganization;

    protected $table = 'hookamatic_event_types';

    protected $fillable = [
        'name',
        'description',
        'organization_id',
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
     * hookamatic_subscriber_events is a genuine many-to-many join table (it has FKs to
     * both sides), not a linear chain, so this is a BelongsToMany, not a HasManyThrough.
     *
     * @return BelongsToMany<Subscribers\Subscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(
            Subscribers\Subscriber::class,
            'hookamatic_subscriber_events',
            'event_type_id',
            'subscriber_id'
        )->withPivot(['priority', 'response_protocol_reference'])->withTimestamps();
    }

    /**
     * @return HasMany<Subscribers\Event>
     */
    public function subscriberLinks(): HasMany
    {
        return $this->hasMany(Subscribers\Event::class, 'event_type_id');
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
