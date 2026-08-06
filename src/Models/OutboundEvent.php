<?php

namespace VanDmade\Hookamatic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use VanDmade\Hookamatic\Enums\InboundStatus;

class OutboundEvent extends Model
{

    protected $table = 'hookamatic_outbound_events';

    protected $fillable = [
        'created_by',
        'event_type_id',
        'payload',
        'caller_file',
        'caller_class',
        'caller_method',
        'caller_line',
        'closed_at',
        'trace',
    ];

    protected $casts = [
        'payload' => 'array',
        'closed_at' => 'datetime',
    ];

    protected $hidden = [
        'created_by',
        'event_type_id',
        'payload',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
            // Defaults the status to pending if not set.
            if (is_null($model->status)) {
                $model->status = InboundStatus::PENDING;
            }
        });
    }

    public function trace(): Attribute
    {
        return Attribute::make(
            set: function($trace) {
                return [
                    'caller_file' => $trace['file'] ?? null,
                    'caller_class' => $trace['class'] ?? null,
                    'caller_method' => $trace['function'] ?? null,
                    'caller_line' => $trace['line'] ?? null,
                ];
            },
        );
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'event_id');
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
