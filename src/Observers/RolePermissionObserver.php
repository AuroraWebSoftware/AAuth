<?php

namespace AuroraWebSoftware\AAuth\Observers;

use AuroraWebSoftware\AAuth\Events\PermissionAddedEvent;
use AuroraWebSoftware\AAuth\Events\PermissionRemovedEvent;
use AuroraWebSoftware\AAuth\Events\PermissionUpdatedEvent;
use AuroraWebSoftware\AAuth\Models\RolePermission;

class RolePermissionObserver
{
    public function created(RolePermission $permission): void
    {
        $this->clearAAuthContext();
        event(new PermissionAddedEvent(
            $permission->role,
            $permission->permission,
            $permission->parameters
        ));
    }

    public function updated(RolePermission $permission): void
    {
        $this->clearAAuthContext();
        event(new PermissionUpdatedEvent(
            $permission->role,
            $permission->permission,
            $permission->parameters,
            $permission->getOriginal('parameters')
        ));
    }

    public function deleted(RolePermission $permission): void
    {
        $this->clearAAuthContext();
        event(new PermissionRemovedEvent(
            $permission->role,
            $permission->permission
        ));
    }

    /**
     * Refresh the live AAuth request context so a permission change is
     * reflected within the same request.
     */
    protected function clearAAuthContext(): void
    {
        try {
            if (app()->bound('aauth')) {
                app('aauth')->clearContext();
            }
        } catch (\Throwable $e) {
            // No resolvable AAuth instance (seeder/console/queue) — nothing to clear.
        }
    }
}
