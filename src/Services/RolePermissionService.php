<?php

namespace Aurora\AAuth\Services;

use Aurora\AAuth\Exceptions\InvalidOrganizationNodeException;
use Aurora\AAuth\Exceptions\InvalidRoleException;
use Aurora\AAuth\Exceptions\InvalidUserException;
use Aurora\AAuth\Http\Requests\StoreRoleRequest;
use Aurora\AAuth\Http\Requests\UpdateRoleRequest;
use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Models\Role;
use Aurora\AAuth\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RolePermission Data Service
 */
class RolePermissionService
{
    /**
     * Creates a Perm. with given array
     * @param array $role
     * @param bool $withValidation
     * @return Role
     * @throws ValidationException
     */
    public function createRole(array $role, bool $withValidation = true): Role
    {
        if ($withValidation) {
            $validator = Validator::make($role, StoreRoleRequest::$rules);

            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                throw new ValidationException($validator, 'Invalid Update Organization Node Request, ' . $message);
            }
        }

        return Role::create($role);
    }

    /**
     * Updates a Perm.
     * @param array $role
     * @param int $id
     * @param bool $withValidation
     * @return Role|null
     */
    public function updateRole(array $role, int $id, bool $withValidation = true): ?Role
    {
        if ($withValidation) {
            $validator = Validator::make($role, UpdateRoleRequest::$rules);

            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                abort(422, 'Invalid Update Role Request, ' . $message);
            }
        }
        $roleModel = Role::find($id);

        return $roleModel->update($role) ? $roleModel : null;
    }

    /**
     * deletes the role.
     * @param int $id
     * @return bool|null
     */
    public function deleteRole(int $id): ?bool
    {
        return Role::find($id)->delete();
    }

    /**
     * activates the roles
     * @param int $roleId
     * @return bool
     */
    public function activateRole(int $roleId): bool
    {
        $role = Role::find($roleId);
        $role->status = 'active';

        return $role->save();
    }

    /**
     * deactivates the roles
     * @param int $roleId
     * @return bool
     */
    public function deactivateRole(int $roleId): bool
    {
        $roleId = Role::find($roleId);
        $roleId->status = 'passive';

        return $roleId->save();
    }

    /**
     * @param string|array $permissionOrPermissions
     * @param int $roleId
     * @return bool
     */
    public function attachPermissionToRole(string|array $permissionOrPermissions, int $roleId): bool
    {
        $roleId = Role::find($roleId)->id;

        if (is_array($permissionOrPermissions)) {
            foreach ($permissionOrPermissions as $permission) {
                $this->attachPermissionToRole($permission, $roleId);
            }
        } else {
            $permissionQueryBuilder = DB::table('role_permission')
                ->where('role_id', $roleId)
                ->where('permission', $permissionOrPermissions);

            if ($permissionQueryBuilder->doesntExist()) {
                return DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission' => $permissionOrPermissions,
                ]);
            }
        }

        return true;
    }

    /**
     * @param string|array $permissions
     * @param int $roleId
     * @return bool
     */
    public function detachPermissionFromRole(string|array $permissions, int $roleId): bool
    {
        $roleId = Role::find($roleId)->id;

        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                $this->detachPermissionFromRole($permission, $roleId);
            }
        } else {
            DB::table('role_permission')->where([
                'role_id' => $roleId,
                'permission' => $permissions,
            ])->delete();
        }

        return true;
    }

    /**
     * @param int $roleId
     * @return bool
     */
    public function detachAllPermissionsFromRole(int $roleId): bool
    {
        $roleId = Role::find($roleId)->id;

        DB::table('role_permission')->where([
            'role_id' => $roleId,
        ])->delete();

        return true;
    }

    /**
     * @param array $permissions
     * @param int $roleId
     * @return bool
     * @throws Throwable
     */
    public function syncPermissionsOfRole(array $permissions, int $roleId): bool
    {
        // todo need refactor
        $role = Role::find($roleId);
        throw_if($role == null, new InvalidRoleException());

        $detached = $this->detachAllPermissionsFromRole($roleId);
        $attached = $this->attachPermissionToRole($permissions, $roleId);

        return ($attached && $detached);
    }

    /**
     * @param int $userId
     * @param array $roleIdOrIds
     * @return array
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
            ->exists(), new InvalidUserException());

        throw_unless(Role::whereId($roleIdOrIds)
            ->where('type', '=', 'system')
            ->exists(), new InvalidRoleException());

        return User::find($userId)->system_roles()->sync($roleIdOrIds, false);
    }

    /**
     * @param int $userId
     * @param int $roleIdOrIds
     * @return int
     * @throws Throwable
     */
    public function detachSystemRoleFromUser(array|int $roleIdOrIds, int $userId): int
    {
        if (! is_array($roleIdOrIds)) {
            $tempRoleId[0] = $roleIdOrIds;
            $roleIdOrIds = $tempRoleId;
        }

        throw_unless(User::whereId($userId)
            ->exists(), new InvalidUserException());

        throw_unless(Role::whereId($roleIdOrIds)
            ->where('type', '=', 'system')
            ->exists(), new InvalidRoleException());

        return User::find($userId)->system_roles()->detach($roleIdOrIds);
    }

    /**
     * @param int $userId
     * @param array $roleIds
     * @return array
     */
    public function syncUserSystemRoles(int $userId, array $roleIds): array
    {
        // todo
        // to be unit tested
        return User::find($userId)->system_roles()->sync($roleIds);
    }

    /**
     * it makes organization insert and return the pivot table id's
     * @param int $userId
     * @param int $roleId
     * @param int $organizationNodeId
     * @return bool
     * @throws Throwable
     */
    public function attachOrganizationRoleToUser(int $organizationNodeId, int $roleId, int $userId): bool
    {
        // todo burası belki user trait'i ile yapılabilir ?
        throw_unless(User::whereId($userId)
            ->exists(), new InvalidUserException());

        throw_unless(Role::whereId($roleId)
            ->where('type', '=', 'organization')
            ->exists(), new InvalidRoleException());

        throw_unless(OrganizationNode::whereId($organizationNodeId)
            ->exists(), new InvalidOrganizationNodeException());

        return DB::table('user_role_organization_node')
            ->updateOrInsert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'organization_node_id' => $organizationNodeId,
            ]);
    }

    /**
     * @param int $userId
     * @param int $roleId
     * @param int $organizationNodeId
     * @return int
     * @throws Throwable
     */
    public function detachOrganizationRoleFromUser(int $userId, int $roleId, int $organizationNodeId): int
    {
        // todo burası belki user trait'i ile yapılabilir ?
        throw_unless(User::whereId($userId)
            ->exists(), new InvalidUserException());

        throw_unless(Role::whereId($roleId)
            ->where('type', '=', 'organization')
            ->exists(), new InvalidRoleException());

        throw_unless(OrganizationNode::whereId($organizationNodeId)
            ->exists(), new InvalidOrganizationNodeException());

        return DB::table('user_role_organization_node')
            ->where([
                'user_id' => $userId,
                'role_id' => $roleId,
                'organization_node_id' => $organizationNodeId,])
            ->delete();
        // todo attach ve sync ile olmayacak gibi direk db query yazmank lazım
    }
}
