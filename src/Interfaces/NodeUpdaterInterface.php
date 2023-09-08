<?php

namespace AuroraWebSoftware\AAuth\Interfaces;

use AuroraWebSoftware\AAuth\Models\OrganizationNode;

interface NodeUpdaterInterface
{
    public function updateNodePath(OrganizationNode $node): void;
}
