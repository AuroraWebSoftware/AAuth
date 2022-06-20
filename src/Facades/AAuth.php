<?php

namespace Aurora\AAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * AAuth Facade
 * @see \Aurora\AAuth\AAuth
 * @method static passOrAbort(string $string, string $message = 'No Permission')
 * @method static can(string $string)
 * @method static organizationNodes(bool $includeRootNode = false, ?string $modelType = null): \Illuminate\Support\Collection
 * @method static organizationPermissions(): \Illuminate\Support\Collection|array
 * @method static organizationNode(int $nodeId, ?string $modelType = null): OrganizationNode|array|Collection|Model
 */

class AAuth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'aauth';
    }
}
