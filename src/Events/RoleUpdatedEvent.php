<?php

namespace AuroraWebSoftware\AAuth\Events;

use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleUpdatedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Role $role
    ) {}
}
