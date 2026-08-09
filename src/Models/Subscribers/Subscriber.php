<?php

namespace VanDmade\Hookamatic\Models\Subscribers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use VanDmade\Hookamatic\Models\Delivery;

class Subscriber extends Model
{

    protected $table = 'hookamatic_subscribers';

    protected $fillable = [
        'name',
        'slug',
        'url',
        'headers',
        'api_key_reference',
        'disabled_by',
        'disabled_at',
        'disabled_reason',
        'disabled_by_system',
        'expires_at',
        'last_called_at',
        'rate_limit_max',
        'rate_limit_interval_seconds',
        'organization_id',
        'created_by',
    ];

    protected $casts = [
        'disabled_at' => 'datetime',
        'disabled_by_system' => 'boolean',
        'expires_at' => 'datetime',
        'last_called_at' => 'datetime',
        'signing_secret' => 'encrypted',
    ];

    protected $hidden = [
        'signing_secret',
        'created_by',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
            // TODO :: Add to configuration file
            $expiresAt = now()->addYear();
            $model->expires_at = $model->expires_at ?? $expiresAt;
            if (empty($model->signing_secret)) {
                $model->signing_secret = $model->generateSigningSecret();
            }
            if (empty($model->slug) && !empty($model->name)) {
                $model->slug = strtolower(Str::slug($model->name, '_'));
            }
        });
    }

    protected function generateSigningSecret(): string
    {
        // Most secure way to generate a random string in PHP.
        return Str::random(64);
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function headers(): Attribute
    {
        return Attribute::make(
            set: function($headers) {
                foreach ($headers as $key => $value) {
                    // TODO :: Remove headers that are not allowed to be set by the user
                }
                return json_encode($headers);
            },
            get: function($headers) {
                return json_decode($headers, true);
            }
        );
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'subscriber_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(config('hookamatic.organization_model'), 'organization_id');
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
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'disabled_by');
    }

}
