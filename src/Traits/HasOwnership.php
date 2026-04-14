<?php

namespace Laraditz\Jaga\Traits;

trait HasOwnership
{
    public function getOwnerKeyAttribute(): string
    {
        return property_exists($this, 'ownerKey') ? $this->ownerKey : 'user_id';
    }

    public function getOwnershipRequiredAttribute(): bool
    {
        return property_exists($this, 'ownershipRequired') ? $this->ownershipRequired : true;
    }

    public function getOwnerKey(): string
    {
        return property_exists($this, 'ownerKey') ? $this->ownerKey : 'user_id';
    }

    public function isOwnershipRequired(): bool
    {
        return property_exists($this, 'ownershipRequired') ? $this->ownershipRequired : true;
    }

    public function getOwnerModel(): string
    {
        return property_exists($this, 'ownerModel') ? $this->ownerModel : config('jaga.ownership.owner_model');
    }
}
