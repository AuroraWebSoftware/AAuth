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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\AuroraWebSoftware\AAuth\Models\Role, \AuroraWebSoftware\AAuth\Models\User, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function roles(): BelongsToMany;
}
