<?php

namespace AuroraWebSoftware\AAuth\Traits;

use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;
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

    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities)) {
            // First check if AAuth has this permission
            try {
                if (app('aauth')->can($abilities)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // If AAuth is not properly initialized, fall through to Laravel's Gate
            }

            // If AAuth doesn't have this permission, delegate to Laravel's Gate system
            // This allows Laravel's native gates and policies to be checked
            return parent::can($abilities, $arguments);
        }

        if (is_array($abilities)) {
            foreach ($abilities as $ability) {
                if (! $this->can($ability, $arguments)) {
                    return false;
                }
            }

            return true;
        }

        return parent::can($abilities, $arguments);
    }
}
