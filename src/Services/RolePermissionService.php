<?php

namespace AuroraWebSoftware\AAuth\Services;

use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidRoleException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidUserException;
use AuroraWebSoftware\AAuth\Http\Requests\StoreRoleRequest;
use AuroraWebSoftware\AAuth\Http\Requests\UpdateRoleRequest;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RolePermission;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * RolePermission Data Service
 */
class RolePermissionService
{
    /**
     * Creates a Perm. with given array
     *
     *
     * @throws ValidationException
     */
    public function createRole(array $role, bool $withValidation = true): Role
    {
        if ($withValidation) {
            $validator = Validator::make($role, StoreRoleRequest::$rules);

            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                throw new ValidationException($validator, new Response($message, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }

        return Role::create($role);
    }

    /**
     * Updates a Perm.
     */
    public function updateRole(array $role, int $id, bool $withValidation = true): ?Role
    {
        if ($withValidation) {
            $validator = Validator::make($role, UpdateRoleRequest::$rules);

            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                abort(422, 'Invalid Update Role Request, '.$message);
            }
        }
        $roleModel = Role::find($id);

        return $roleModel->update($role) ? $roleModel : null;
    }

    /**
     * deletes the role.
     */
    public function deleteRole(int $id): ?bool
    {
        return Role::find($id)->delete();
    }

    /**
     * activates the roles
     */
    public function activateRole(int $roleId): bool
    {
        $role = Role::find($roleId);
        $role->status = 'active';

        return $role->save();
    }

    /**
     * deactivates the roles
     */
    public function deactivateRole(int $roleId): bool
    {
        $roleId = Role::find($roleId);
        $roleId->status = 'passive';

        return $roleId->save();
    }

    public function attachPermissionToRole(string|array $permissionOrPermissions, int $roleId): bool
    {
        $roleId = Role::find($roleId)->id;

        if (is_array($permissionOrPermissions)) {
            foreach ($permissionOrPermissions as $permission) {
                $this->attachPermissionToRole($permission, $roleId);
            }
        } else {
            // Use Eloquent to trigger observers
            RolePermission::firstOrCreate([
                'role_id' => $roleId,
                'permission' => $permissionOrPermissions,
            ]);
        }

        return true;
    }

    public function detachPermissionFromRole(string|array $permissions, int $roleId): bool
    {
        $roleId = Role::find($roleId)->id;

        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                $this->detachPermissionFromRole($permission, $roleId);
            }
        } else {
            // Use Eloquent to trigger observers
            RolePermission::where([
                'role_id' => $roleId,
                'permission' => $permissions,
            ])->get()->each->delete();
        }

        return true;
    }

    public function detachAllPermissionsFromRole(int $roleId): bool
    {
        $roleId = Role::find($roleId)->id;

        // Use Eloquent to trigger observers
        RolePermission::where('role_id', $roleId)->get()->each->delete();

        return true;
    }

    /**
     * @throws Throwable
     */
    public function syncPermissionsOfRole(array $permissions, int $roleId): bool
    {
        $role = Role::find($roleId);
        throw_if($role == null, new InvalidRoleException);

        return DB::transaction(function () use ($permissions, $roleId) {
            $detached = $this->detachAllPermissionsFromRole($roleId);
            $attached = $this->attachPermissionToRole($permissions, $roleId);

            return $attached && $detached;
        });
    }

    /**
     * @param  array  $roleIdOrIds
     *
     * @throws Throwable
     */
    public function attachSystemRoleToUser(array|int $roleIdOrIds, int $userId): array
    {
        // todo burası belki user trait'i ile yapılabilir ?

        if (! is_array($roleIdOrIds)) {
            $tempRoleId[0] = $roleIdOrIds;
            $roleIdOrIds = $tempRoleId;
        }

        throw_unless(User::whereId($userId)
            ->exists(), new InvalidUserException);

        $roleQuery = Role::whereId($roleIdOrIds);
        if ($this->hasRolesTypeColumn()) {
            $roleQuery->where('type', '=', 'system');
        } else {
            $roleQuery->whereNull('organization_scope_id');
        }
        throw_unless($roleQuery->exists(), new InvalidRoleException);

        $result = User::find($userId)->system_roles()->sync($roleIdOrIds, false);

        // Clear user's switchable_roles cache
        $this->clearUserRoleCache($userId);

        return $result;
    }

    /**
     * @param  int  $roleIdOrIds
     *
     * @throws Throwable
     */
    public function detachSystemRoleFromUser(array|int $roleIdOrIds, int $userId): int
    {
        if (! is_array($roleIdOrIds)) {
            $tempRoleId[0] = $roleIdOrIds;
            $roleIdOrIds = $tempRoleId;
        }

        throw_unless(User::whereId($userId)
            ->exists(), new InvalidUserException);

        // Defensive: check if old 'type' column exists
        $roleQuery = Role::whereId($roleIdOrIds);
        if ($this->hasRolesTypeColumn()) {
            $roleQuery->where('type', '=', 'system');
        } else {
            $roleQuery->whereNull('organization_scope_id');
        }
        throw_unless($roleQuery->exists(), new InvalidRoleException);

        $result = User::find($userId)->system_roles()->detach($roleIdOrIds);

        $this->clearUserRoleCache($userId);

        return $result;
    }

    public function syncUserSystemRoles(int $userId, array $roleIds): array
    {
        // todo
        // to be unit tested
        $result = User::find($userId)->system_roles()->sync($roleIds);

        $this->clearUserRoleCache($userId);

        return $result;
    }

    /**
     * it makes organization insert and return the pivot table id's
     *
     *
     * @throws Throwable
     */
    public function attachOrganizationRoleToUser(int $organizationNodeId, int $roleId, int $userId): bool
    {
        // todo burası belki user trait'i ile yapılabilir ?
        throw_unless(User::whereId($userId)
            ->exists(), new InvalidUserException);

        $roleQuery = Role::whereId($roleId);
        if ($this->hasRolesTypeColumn()) {
            $roleQuery->where('type', '=', 'organization');
        } else {
            $roleQuery->whereNotNull('organization_scope_id');
        }
        throw_unless($roleQuery->exists(), new InvalidRoleException);

        throw_unless(OrganizationNode::whereId($organizationNodeId)
            ->exists(), new InvalidOrganizationNodeException);

        $result = DB::table('user_role_organization_node')
            ->updateOrInsert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'organization_node_id' => $organizationNodeId,
            ]);

        $this->clearUserRoleCache($userId);

        return $result;
    }

    /**
     * Detach an organization role from a user.
     *
     * @deprecated since 21.1.0 Parameter order is inconsistent with
     *             {@see self::attachOrganizationRoleToUser()}. Use
     *             {@see self::detachOrganizationRoleFromUserBy()} which takes
     *             arguments in the aligned order ($organizationNodeId, $roleId, $userId).
     *             This historic-order method continues to work and emits no
     *             runtime notice; it will be removed in the next major release.
     *
     * @throws Throwable
     */
    public function detachOrganizationRoleFromUser(int $userId, int $roleId, int $organizationNodeId): int
    {
        // todo burası belki user trait'i ile yapılabilir ?
        throw_unless(User::whereId($userId)
            ->exists(), new InvalidUserException);

        $roleQuery = Role::whereId($roleId);
        if ($this->hasRolesTypeColumn()) {
            $roleQuery->where('type', '=', 'organization');
        } else {
            $roleQuery->whereNotNull('organization_scope_id');
        }
        throw_unless($roleQuery->exists(), new InvalidRoleException);

        throw_unless(OrganizationNode::whereId($organizationNodeId)
            ->exists(), new InvalidOrganizationNodeException);

        $result = DB::table('user_role_organization_node')
            ->where([
                'user_id' => $userId,
                'role_id' => $roleId,
                'organization_node_id' => $organizationNodeId, ])
            ->delete();

        $this->clearUserRoleCache($userId);

        return $result;
        // todo attach ve sync ile olmayacak gibi direk db query yazmank lazım
    }

    /**
     * Detach an organization role from a user using the canonical parameter
     * order that matches {@see self::attachOrganizationRoleToUser()}.
     *
     * Prefer this method over the legacy {@see self::detachOrganizationRoleFromUser()}
     * which keeps the historic, inconsistent parameter order for backward
     * compatibility and will be removed in the next major release.
     *
     *
     * @throws Throwable
     */
    public function detachOrganizationRoleFromUserBy(int $organizationNodeId, int $roleId, int $userId): int
    {
        throw_unless(User::whereId($userId)->exists(), new InvalidUserException);

        $roleQuery = Role::whereId($roleId);
        if ($this->hasRolesTypeColumn()) {
            $roleQuery->where('type', '=', 'organization');
        } else {
            $roleQuery->whereNotNull('organization_scope_id');
        }
        throw_unless($roleQuery->exists(), new InvalidRoleException);

        throw_unless(
            OrganizationNode::whereId($organizationNodeId)->exists(),
            new InvalidOrganizationNodeException
        );

        $result = DB::table('user_role_organization_node')
            ->where([
                'user_id' => $userId,
                'role_id' => $roleId,
                'organization_node_id' => $organizationNodeId,
            ])
            ->delete();

        $this->clearUserRoleCache($userId);

        return $result;
    }

    /**
     * Check if type column exists in roles table (for backward compatibility)
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
     * Clear user's role-related cache
     */
    protected function clearUserRoleCache(int $userId): void
    {
        if (! config('aauth-advanced.cache.enabled', false)) {
            return;
        }

        $prefix = config('aauth-advanced.cache.prefix', 'aauth');
        $store = config('aauth-advanced.cache.store');
        $cache = $store ? Cache::store($store) : Cache::store();

        $cache->forget("{$prefix}:user:{$userId}:switchable_roles");
    }
}
