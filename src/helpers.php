<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;

if (! function_exists('aauth')) {
    function aauth(): AAuth
    {
        return app('aauth');
    }
}

if (! function_exists('aauth_can')) {
    function aauth_can(string $permission, mixed ...$arguments): bool
    {
        return app('aauth')->can($permission, ...$arguments);
    }
}

if (! function_exists('aauth_has_role')) {
    function aauth_has_role(string $roleName): bool
    {
        return app('aauth')->currentRole()?->name === $roleName;
    }
}

if (! function_exists('aauth_active_role')) {
    function aauth_active_role(): ?Role
    {
        return app('aauth')->currentRole();
    }
}

if (! function_exists('aauth_active_organization')) {
    function aauth_active_organization(): ?OrganizationNode
    {
        $nodeIds = app('aauth')->organizationNodeIds();
        if (empty($nodeIds)) {
            return null;
        }

        return OrganizationNode::find($nodeIds[0]);
    }
}

if (! function_exists('aauth_is_super_admin')) {
    function aauth_is_super_admin(): bool
    {
        return app('aauth')->isSuperAdmin();
    }
}

if (! function_exists('aauth_for_panel')) {
    function aauth_for_panel(?string $panelId = null): AAuth
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $roleId = \Illuminate\Support\Facades\Session::get('roleId');

        if ($panelId === null) {
            $panelId = AAuth::detectCurrentPanelId();
        }

        return new AAuth($user, $roleId, $panelId);
    }
}

if (! function_exists('aauth_panel_roles')) {
    function aauth_panel_roles(?string $panelId = null): \Illuminate\Database\Eloquent\Collection
    {
        return aauth_for_panel($panelId)->switchableRolesForCurrentPanel();
    }
}

if (! function_exists('aauth_in_panel')) {
    function aauth_in_panel(string $panelId): bool
    {
        return app('aauth')->isInPanel($panelId);
    }
}

if (! function_exists('aauth_current_panel')) {
    function aauth_current_panel(): ?string
    {
        return app('aauth')->getPanelId();
    }
}
