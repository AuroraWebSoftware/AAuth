<?php

namespace Aurora\AAuth\Traits;

use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * @return BelongsToMany
     */
    public function system_roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_organization_node')
            ->where('type', 'system');
    }

    /**
     * @return BelongsToMany
     */
    public function organization_roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_organization_node')
            ->where('type', 'organization');
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
}
