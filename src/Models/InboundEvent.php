<?php

namespace VanDmade\Hookamatic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use VanDmade\Hookamatic\Enums\InboundStatus;

class InboundEvent extends Model
{

    protected $table = 'hookamatic_inbound_events';

    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'headers',
        'payload',
        'ip_address',
        'status',
        'attempt_counter',
        'duration_ms',
        'processed_at',
        'failed_at',
        // Not an actual column, but we want to be able to mass-assign it for convenience.
        'request',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $hidden = [
        'headers',
        'payload',
    ];

    public static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if ($model->status == InboundStatus::PROCESSED) {
                $model->processed_at = now();
            } elseif ($model->status == InboundStatus::FAILED) {
                $model->failed_at = now();
            }
        });
    }

    /**
     * @return Attribute<mixed, mixed>
     */
    public function request(): Attribute
    {
        return Attribute::make(
            set: function($request) {
                return [
                    'headers' => $request->headers->all(),
                    'payload' => json_decode($request->getContent(), true),
                    'ip_address' => $request->ip(),
                ];
            }
        );
    }

}
