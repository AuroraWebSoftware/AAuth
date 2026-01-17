<?php

namespace AuroraWebSoftware\AAuth\Observers;

use AuroraWebSoftware\AAuth\Models\RolePermission;
use Illuminate\Support\Facades\Cache;

class RolePermissionObserver
{
    public function created(RolePermission $permission): void
    {
        $this->clearPermissionCache($permission);
    }

    public function updated(RolePermission $permission): void
    {
        $this->clearPermissionCache($permission);
    }

    public function deleted(RolePermission $permission): void
    {
        $this->clearPermissionCache($permission);
    }

    protected function clearPermissionCache(RolePermission $permission): void
    {
        if (! config('aauth.cache.enabled', true)) {
            return;
        }

        $prefix = config('aauth.cache.prefix', 'aauth');

        Cache::forget("{$prefix}:role:{$permission->role_id}:permissions");
    }
}
