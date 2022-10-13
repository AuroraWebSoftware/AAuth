<?php

namespace AuroraWebSoftware\AAuth\Traits;

use AuroraWebSoftware\AAuth\Scopes\AAuthOrganizationNodeScope;

trait AAuthABACModel
{
    /**
     * @return void
     */
    public static function bootAAuthABACModel(): void
    {
        static::addGlobalScope(new AAuthOrganizationNodeScope());
    }
}
