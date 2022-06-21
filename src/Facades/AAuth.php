<?php

namespace Aurora\AAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * AAuth Facade
 * @see \Aurora\AAuth\AAuth
 * @method static switchableRoles(): array|Collection|\Illuminate\Support\Collection
 * @method static permissions(): \Illuminate\Support\Collection|array
 * @method static organizationPermissions(): \Illuminate\Support\Collection|array
 * @method static systemPermissions(): array|\Illuminate\Support\Collection
 * @method static can(string $string)
 * @method static passOrAbort(string $string, string $message = 'No Permission')
 * @method static organizationNodes(bool $includeRootNode = false, ?string $modelType = null): \Illuminate\Support\Collection
 * @method static organizationNode(int $nodeId, ?string $modelType = null): OrganizationNode|array|Collection|Model
 * @method static descendant(int $rootNodeId, int $childNodeId): bool
 */

class AAuth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'aauth';
    }
}
