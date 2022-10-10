<?php

namespace AuroraWebSoftware\AAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * AAuth Facade
 * @see AuroraWebSoftware
 * @static switchableRoles(): array|Collection|\Illuminate\Support\Collection
 * @static permissions(): \Illuminate\Support\Collection|array
 * @static organizationPermissions(): \Illuminate\Support\Collection|array
 * @static systemPermissions(): array|\Illuminate\Support\Collection
 * @static can(string $string)
 * @static passOrAbort(string $string, string $message = 'No Permission')
 * @method static organizationNodes(bool $includeRootNode = false, ?string $modelType = null): \Illuminate\Support\Collection
 * @static organizationNode(int $nodeId, ?string $modelType = null): OrganizationNode|array|Collection|Model
 * @static descendant(int $rootNodeId, int $childNodeId): bool
 * @static switchableRolesStatic(int $userId): array|Collection|\Illuminate\Support\Collection
 * @method static currentRole(): ?Role
 */

class AAuth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'aauth';
    }
}
