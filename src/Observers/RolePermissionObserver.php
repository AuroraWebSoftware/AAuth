<?php

namespace AuroraWebSoftware\AAuth\Observers;

use AuroraWebSoftware\AAuth\Events\PermissionAddedEvent;
use AuroraWebSoftware\AAuth\Events\PermissionRemovedEvent;
use AuroraWebSoftware\AAuth\Events\PermissionUpdatedEvent;
use AuroraWebSoftware\AAuth\Models\RolePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;

class RolePermissionObserver
{
    public function created(RolePermission $permission): void
    {
        $this->clearPermissionCache($permission);
        event(new PermissionAddedEvent(
            $permission->role,
            $permission->permission,
            $permission->parameters
        ));
    }

    public function updated(RolePermission $permission): void
    {
        $this->clearPermissionCache($permission);
        event(new PermissionUpdatedEvent(
            $permission->role,
            $permission->permission,
            $permission->parameters,
            $permission->getOriginal('parameters')
        ));
    }

    public function deleted(RolePermission $permission): void
    {
        $this->clearPermissionCache($permission);
        event(new PermissionRemovedEvent(
            $permission->role,
            $permission->permission
        ));
    }

    protected function clearPermissionCache(RolePermission $permission): void
    {
        // Clear AAuth instance's request cache and context
        try {
            if (app()->bound('aauth')) {
                app('aauth')->clearContext();
            }
        } catch (\Throwable $e) {
            // AAuth not initialized yet, just clear context directly
            Context::forgetHidden('aauth_context');
        }

        if (! config('aauth-advanced.cache.enabled', false)) {
            return;
        }

        $prefix = config('aauth-advanced.cache.prefix', 'aauth');

        Cache::forget("{$prefix}:role:{$permission->role_id}");

        $role = $permission->role;
        if ($role && isset($role->panel_id)) {
            Cache::forget("{$prefix}:role:{$permission->role_id}:panel:{$role->panel_id}");
        }

        Cache::forget("{$prefix}:role:{$permission->role_id}:permissions");
    }
}
