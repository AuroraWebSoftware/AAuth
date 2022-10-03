<?php

namespace AuroraWebSoftware\AAuth\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 */
interface AAuthUserContract
{
    public function roles(): BelongsToMany;
}
