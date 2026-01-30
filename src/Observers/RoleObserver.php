<?php

namespace AuroraWebSoftware\AAuth\Observers;

use AuroraWebSoftware\AAuth\Events\RoleCreatedEvent;
use AuroraWebSoftware\AAuth\Events\RoleDeletedEvent;
use AuroraWebSoftware\AAuth\Events\RoleUpdatedEvent;
use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RoleObserver
{
    public function created(Role $role): void
    {
        event(new RoleCreatedEvent($role));
    }

    public function updated(Role $role): void
    {
        $this->clearRoleCache($role);
        event(new RoleUpdatedEvent($role));
    }

    public function deleted(Role $role): void
    {
        $this->clearRoleCache($role);
        event(new RoleDeletedEvent($role));
    }

    protected function clearRoleCache(Role $role): void
    {
        if (! config('aauth-advanced.cache.enabled', false)) {
            return;
        }

        $prefix = config('aauth-advanced.cache.prefix', 'aauth');

        Cache::forget("{$prefix}:role:{$role->id}");
        Cache::forget("{$prefix}:role:{$role->id}:permissions");
        Cache::forget("{$prefix}:role:{$role->id}:abac_rules");

        // Clear switchable roles cache for all users with this role
        $userIds = DB::table('user_role_organization_node')
            ->where('role_id', $role->id)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            Cache::forget("{$prefix}:user:{$userId}:switchable_roles");
        }
    }
}
