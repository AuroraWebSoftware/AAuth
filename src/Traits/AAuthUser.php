<?php

namespace AuroraWebSoftware\AAuth\Traits;

use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait AAuthUser
{
    /**
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_organization_node');
    }

    /**
     * @return Collection
     */
    public function rolesWithOrganizationNodes(): Collection
    {
        $rolesCollection = collect();
        $rolesWithOrganizationNodes = DB::table('user_role_organization_node')->where('user_id', '=', $this->id)->get();

        foreach ($rolesWithOrganizationNodes as $rolesWithOrganizationNode) {
            $role = Role::find($rolesWithOrganizationNode->role_id);
            $role->organizationNode = OrganizationNode::find($rolesWithOrganizationNode->organization_node_id);

            $rolesCollection->push($role);
        }

        return $rolesCollection;
    }

    /**
     * Get system roles (global roles not tied to organization)
     * Defensive: supports both old 'type' column and new organization_scope_id approach
     *
     * @return BelongsToMany
     */
    public function system_roles(): BelongsToMany
    {
        $query = $this->belongsToMany(Role::class, 'user_role_organization_node');

        // Defensive: check if old 'type' column exists
        if ($this->hasRolesTypeColumn()) {
            return $query->where('type', 'system');
        }

        return $query->whereNull('roles.organization_scope_id');
    }

    /**
     * Get organization roles (roles tied to an organization scope)
     * Defensive: supports both old 'type' column and new organization_scope_id approach
     *
     * @return BelongsToMany
     */
    public function organization_roles(): BelongsToMany
    {
        $query = $this->belongsToMany(Role::class, 'user_role_organization_node');

        // Defensive: check if old 'type' column exists
        if ($this->hasRolesTypeColumn()) {
            return $query->where('type', 'organization');
        }

        return $query->whereNotNull('roles.organization_scope_id');
    }

    /**
     * Check if type column exists in roles table (for backward compatibility)
     *
     * @return bool
     */
    protected function hasRolesTypeColumn(): bool
    {
        static $hasType = null;

        if ($hasType === null) {
            $hasType = Schema::hasColumn('roles', 'type');
        }

        return $hasType;
    }

    /**
     * @return int
     */
    public function getAssignedUserCountAttribute(): int
    {
        return DB::table('user_role_organization_node')
            ->where('user_id', $this->id)->count();
    }

    /**
     * @return bool
     */
    public function getDeletableAttribute(): bool
    {
        // todo new syntax
        return $this->getAssignedUserCountAttribute() == 0;
    }

    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities)) {
            return app('aauth')->can($abilities);
        }

        if (is_array($abilities)) {
            foreach ($abilities as $ability) {
                if (! app('aauth')->can($ability)) {
                    return false;
                }
            }

            return true;
        }

        return parent::can($abilities, $arguments);
    }
}
