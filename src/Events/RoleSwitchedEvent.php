<?php

namespace AuroraWebSoftware\AAuth\Events;

use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleSwitchedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public Role $newRole,
        public ?Role $oldRole = null,
        public ?OrganizationNode $organizationNode = null
    ) {}
}
