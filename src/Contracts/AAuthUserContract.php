<?php

namespace AuroraWebSoftware\AAuth\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read int $id
 */
interface AAuthUserContract
{
    public function roles(): BelongsToMany;
}
