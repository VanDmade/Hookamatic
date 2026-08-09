<?php

namespace VanDmade\Hookamatic\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VanDmade\Hookamatic\Scopes\OrganizationScope;

trait HasOrganization
{

    public static function bootHasOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(config('hookamatic.organization_model'), 'organization_id');
    }

}
