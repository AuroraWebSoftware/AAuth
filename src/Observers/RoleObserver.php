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
        $store = config('aauth-advanced.cache.store');
        $cache = $store ? Cache::store($store) : Cache::store();

        $cache->forget("{$prefix}:role:{$role->id}");
        $cache->forget("{$prefix}:role:{$role->id}:permissions");
        $cache->forget("{$prefix}:role:{$role->id}:abac_rules");

        // Clear switchable roles cache for all users with this role
        $userIds = DB::table('user_role_organization_node')
            ->where('role_id', $role->id)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $cache->forget("{$prefix}:user:{$userId}:switchable_roles");
        }
    }
}
