<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;

if (!function_exists('aauth')) {
    function aauth(): AAuth
    {
        return app('aauth');
    }
}

if (!function_exists('aauth_can')) {
    function aauth_can(string $permission, mixed ...$arguments): bool
    {
        return app('aauth')->can($permission, ...$arguments);
    }
}

if (!function_exists('aauth_has_role')) {
    function aauth_has_role(string $roleName): bool
    {
        return app('aauth')->currentRole()?->name === $roleName;
    }
}

if (!function_exists('aauth_active_role')) {
    function aauth_active_role(): ?Role
    {
        return app('aauth')->currentRole();
    }
}

if (!function_exists('aauth_active_organization')) {
    function aauth_active_organization(): ?OrganizationNode
    {
        $nodeIds = app('aauth')->organizationNodeIds();
        if (empty($nodeIds)) {
            return null;
        }
        return OrganizationNode::find($nodeIds[0]);
    }
}

if (!function_exists('aauth_is_super_admin')) {
    function aauth_is_super_admin(): bool
    {
        return app('aauth')->isSuperAdmin();
    }
}
