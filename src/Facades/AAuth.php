<?php

namespace AuroraWebSoftware\AAuth\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;

/**
 * AAuth Facade
 *
 * @see \AuroraWebSoftware\AAuth\AAuth
 *
 * @method static array<int, \AuroraWebSoftware\AAuth\Models\Role>|\Illuminate\Support\Collection<int, \AuroraWebSoftware\AAuth\Models\Role> switchableRoles()
 * @method static array<int, mixed> permissions()
 * @method static array<int, mixed> organizationPermissions()
 * @method static array<int, mixed> systemPermissions()
 * @method static array<int, int>|null organizationNodeIds()
 * @method static bool can(string $permission, mixed ...$arguments)
 * @method static void passOrAbort(string $permission, string $message = 'No Permission', array<int, mixed> $arguments = [])
 * @method static \Illuminate\Support\Collection<int, \AuroraWebSoftware\AAuth\Models\OrganizationNode> organizationNodes(bool $includeRootNode = false, ?string $modelType = null)
 * @method static \AuroraWebSoftware\AAuth\Models\OrganizationNode organizationNode(int $nodeId, ?string $modelType = null)
 * @method static bool descendant(int $rootNodeId, int $childNodeId)
 * @method static \AuroraWebSoftware\AAuth\Models\Role|null currentRole()
 * @method static array<string, mixed>|null ABACRules(string $modelType)
 * @method static Builder<\AuroraWebSoftware\AAuth\Models\OrganizationNode> organizationNodesQuery(bool $includeRootNode = false, ?string $modelType = null)
 */
class AAuth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'aauth';
    }
}
