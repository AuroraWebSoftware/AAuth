<?php

namespace AuroraWebSoftware\AAuth\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property $role
 */
interface AAuthUserContract
{
    public function roles(): BelongsToMany;
}
