<?php

namespace AuroraWebSoftware\AAuth\Events;

use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionRemovedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Role $role,
        public string $permission
    ) {}
}
