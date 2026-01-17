<?php

namespace AuroraWebSoftware\AAuth\Events;

use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleRemovedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public Role $role,
        public ?OrganizationNode $organizationNode = null
    ) {}
}
