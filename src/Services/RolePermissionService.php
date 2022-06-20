<?php

namespace Aurora\AAuth\Services;


use Aurora\AAuth\Http\Requests\StoreRoleRequest;
use Aurora\AAuth\Models\Role;
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
    /*

    public function attachPermissionToRole(int $roleId, array $permissionIds): array
    {
        return Role::find($roleId)->permissions()->sync($permissionIds, false);
    }

    public function detachPermissionToRole(int $roleId, int|array $permissionIds): int
    {
        return Role::find($roleId)->permissions()->detach($permissionIds);
    }


    public function syncRolePermissions(int $roleId, array $permissionIds): array
    {
        return Role::find($roleId)->permissions()->sync($permissionIds);
    }

    */

    /**
     * @param int $userId
     * @param array $roleId
     * @return array
     * @throws Throwable
     */
    public function attachSystemRoleToUser(int $userId, array $roleId): array
    {
        throw_unless(Role::whereId($roleId)
            ->where('type', '=', 'system')
            ->exists(), new UserHasNoAssignedRoleException());

        return User::find($userId)->system_roles()->sync($roleId, false);
    }

    /**
     * @param int $userId
     * @param int $roleId
     * @return int
     * @throws Throwable
     */
    public function detachSystemRoleFromUser(int $userId, int $roleId): int
    {
        throw_unless(Role::whereId($roleId)
            ->where('type', '=', 'system')
            ->exists(), new UserHasNoAssignedRoleException());

        return User::find($userId)->system_roles()->detach($roleId);
    }

    /**
     * @param int $userId
     * @param array $roleIds
     * @return array
     */
    public function syncUserSystemRoles(int $userId, array $roleIds): array
    {
        // todo
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
    public function attachOrganizationRoleToUser(int $userId, int $roleId, int $organizationNodeId): bool
    {
        throw_unless(User::whereId($userId)
            ->exists(), new UserHasNoAssignedRoleException());

        throw_unless(Role::whereId($roleId)
            ->where('type', '=', 'organization')
            ->exists(), new UserHasNoAssignedRoleException());

        throw_unless(OrganizationNode::whereId($organizationNodeId)
            ->exists(), new InvalidOrganizationNodeException());

        throw_unless(
            OrganizationNode::find($organizationNodeId)->organization_scope_id == Role::find($roleId)->organization_scope_id,
            new OrganizationScopesMismatchException()
        );

        return DB::table('user_role_organization_node')
            ->updateOrInsert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'organization_node_id' => $organizationNodeId,
            ]);

        // todo attach ve sync ile olmayacak gibi db query yazmak lazım
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
        throw_unless(User::whereId($userId)
            ->exists(), new InvalidOrganizationNodeException());

        throw_unless(Role::whereId($roleId)
            ->where('type', '=', 'organization')
            ->exists(), new UserHasNoAssignedRoleException());

        throw_unless(OrganizationNode::whereId($organizationNodeId)
            ->exists(), new InvalidOrganizationNodeException());

        return DB::table('user_role_organization_node')
            ->where([
                'user_id' => $userId,
                'role_id' => $roleId,
                'organization_node_id' => $organizationNodeId, ])
            ->delete();
        // todo attach ve sync ile olmayacak gibi direk db query yazmank lazım
    }
}
