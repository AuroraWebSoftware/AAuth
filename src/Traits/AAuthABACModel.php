<?php

namespace AuroraWebSoftware\AAuth\Traits;

use AuroraWebSoftware\AAuth\Scopes\AAuthABACModelScope;

trait AAuthABACModel
{
    public static function bootAAuthABACModel(): void
    {
        static::addGlobalScope(new AAuthABACModelScope);
    }
}
