<?php

namespace AuroraWebSoftware\AAuth\Observers;

use AuroraWebSoftware\AAuth\Events\RoleCreatedEvent;
use AuroraWebSoftware\AAuth\Events\RoleDeletedEvent;
use AuroraWebSoftware\AAuth\Events\RoleUpdatedEvent;
use AuroraWebSoftware\AAuth\Models\Role;

class RoleObserver
{
    public function created(Role $role): void
    {
        event(new RoleCreatedEvent($role));
    }

    public function updated(Role $role): void
    {
        event(new RoleUpdatedEvent($role));
    }

    public function deleted(Role $role): void
    {
        event(new RoleDeletedEvent($role));
    }
}
