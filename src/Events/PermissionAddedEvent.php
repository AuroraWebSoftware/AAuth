<?php

namespace AuroraWebSoftware\AAuth\Events;

use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionAddedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>|null  $parameters
     */
    public function __construct(
        public Role $role,
        public string $permission,
        public ?array $parameters = null
    ) {
    }
}
