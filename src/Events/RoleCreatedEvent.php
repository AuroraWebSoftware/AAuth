<?php

namespace AuroraWebSoftware\AAuth\Events;

use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleCreatedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Role $role
    ) {
    }
}
