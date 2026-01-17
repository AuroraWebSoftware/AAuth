<?php

namespace AuroraWebSoftware\AAuth\Observers;

use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Support\Facades\Cache;

class RoleObserver
{
    public function updated(Role $role): void
    {
        $this->clearRoleCache($role);
    }

    public function deleted(Role $role): void
    {
        $this->clearRoleCache($role);
    }

    protected function clearRoleCache(Role $role): void
    {
        if (! config('aauth.cache.enabled', true)) {
            return;
        }

        $prefix = config('aauth.cache.prefix', 'aauth');

        Cache::forget("{$prefix}:role:{$role->id}:permissions");
        Cache::forget("{$prefix}:role:{$role->id}:abac_rules");

        $role->load('organization_nodes');
        foreach ($role->organization_nodes as $node) {
            $pivotUserId = $node->pivot->user_id ?? null;
            if ($pivotUserId) {
                Cache::forget("{$prefix}:user:{$pivotUserId}:roles");
            }
        }
    }
}
