<?php

namespace AuroraWebSoftware\AAuth\Contracts;

use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read int $id
 */
interface AAuthUserContract
{
    /**
     * @return BelongsToMany<Role>
     */
    public function roles(): BelongsToMany;
}
