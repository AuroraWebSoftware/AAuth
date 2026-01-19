<?php

namespace AuroraWebSoftware\AAuth\Observers;

use AuroraWebSoftware\AAuth\Events\RoleCreatedEvent;
use AuroraWebSoftware\AAuth\Events\RoleDeletedEvent;
use AuroraWebSoftware\AAuth\Events\RoleUpdatedEvent;
use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Support\Facades\Cache;

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

        if (isset($role->panel_id)) {
            Cache::forget("{$prefix}:role:{$role->id}:panel:{$role->panel_id}");
        }

        Cache::forget("{$prefix}:role:{$role->id}:permissions");
        Cache::forget("{$prefix}:role:{$role->id}:abac_rules");

        $role->load('organization_nodes');
        foreach ($role->organization_nodes as $node) {
            $pivotUserId = $node->pivot->user_id ?? null;
            if ($pivotUserId) {
                Cache::forget("{$prefix}:user:{$pivotUserId}:switchable_roles");
            }
        }
    }
}
