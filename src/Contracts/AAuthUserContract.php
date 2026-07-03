<?php

namespace AuroraWebSoftware\AAuth\Contracts;

use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 */
interface AAuthUserContract
{
    /**
     * @return BelongsToMany<Role, User, Pivot>
     */
    public function roles(): BelongsToMany;
}
