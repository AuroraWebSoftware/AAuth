<?php

namespace Aurora\AAuth;

use Aurora\AAuth\Exceptions\InvalidOrganizationNodeException;
use Aurora\AAuth\Exceptions\MissingRoleExcepiton;
use Aurora\AAuth\Exceptions\UserHasNoAssignedRoleException;
use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Models\Role;
use Aurora\AAuth\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

class AAuth
{
    /**
     * @throws Throwable
     */

    /**
     * current logged in user model
     */
    public User $user;

    /**
     * current logged in user's role model
     */
    public Role $role;

    /**
     * @var array|null
     */
    public ?array $organizationNodeIds;

    /**
     * @throws Throwable
     */
    public function __construct(?User $user, ?int $roleId)
    {
        throw_unless($user, new AuthenticationException());
        throw_unless($roleId, new MissingRoleExcepiton());

        // if user dont have this role, not assigned
        throw_if(
            $user->roles->where('id', '=', $roleId)->count() < 1,
            new UserHasNoAssignedRoleException()
        );

        $this->user = $user;
        $this->role = Role::find($roleId);

        throw_unless($this->role, new MissingRoleExcepiton());

        $this->organizationNodeIds = DB::table('user_role_organization_node')
            ->where('user_id', '=', $user->id)
            ->where('role_id', '=', $roleId)
            ->pluck('organization_node_id')->toArray();
    }

    /**
     * @return array|Collection|\Illuminate\Support\Collection
     */
    public function switchableRoles(): array|Collection|\Illuminate\Support\Collection
    {
        return Role::where('uro.user_id', '=', $this->user->id)
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct()
            ->get(['roles.id', 'name']);
    }

    /**
     * Role's all permissions
     * @return array
     */
    public function permissions(): array
    {
        return Role::where('roles.id', '=', $this->role->id)
            ->leftJoin('role_permission as rp', 'rp.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
    }

    /**
     * @return array
     */
    public function organizationPermissions(): array
    {
        return Role::where('roles.id', '=', $this->role->id)
            ->where('type', '=', 'organization')
            ->leftJoin('role_permission as rp', 'rp.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
    }

    /**
     * @return array
     */
    public function systemPermissions(): array
    {
        return Role::where('roles.id', '=', $this->role->id)
            ->where('type', '=', 'system')
            ->leftJoin('role_permission as rp', 'rp.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
    }

    /**
     * check if user can
     * @param string $permission
     * @return bool
     */
    public function can(string $permission): bool
    {
        return Role::where('roles.id', '=', $this->role->id)
                ->leftJoin('role_permission as rp', 'rp.role_id', '=', 'roles.id')
                ->where('rp.permission', '=', $permission)
                ->count() > 0;
    }

    /**
     * @param string $permission
     * @param string $message
     * @return void
     */
    public function passOrAbort(string $permission, string $message = 'No Permission'): void
    {
        // todo mesaj dil dosyasından gelecek.
        if (! $this->can($permission)) {
            abort(ResponseAlias::HTTP_UNAUTHORIZED, $message);
        }
    }

    /**
     * Returns user's current role's authorized organization nodes
     * if model type is given, returns only this model typed nodes.
     * @param bool $includeRootNode
     * @param string|null $modelType
     * @return \Illuminate\Support\Collection
     * @throws Throwable
     */
    public function organizationNodes(bool $includeRootNode = false, ?string $modelType = null): \Illuminate\Support\Collection
    {
        // todo scope eklenecek. $scopeLevel $scopeName
        // todo depth ler eklenecek $maxDepthFromRoot $minDepthFromRoot

        return OrganizationNode::where(function ($query) use ($includeRootNode) {
            foreach ($this->organizationNodeIds as $organizationNodeId) {
                $rootNode = OrganizationNode::find($organizationNodeId);
                throw_unless($rootNode, new InvalidOrganizationNodeException());
                $rootNodeChar = $includeRootNode ? '' : '/';

                $query->orWhere('path', 'like', $rootNode->path . $rootNodeChar . '%');
            }
        })
            ->when($modelType !== null, function ($query) use ($modelType) {
                return $query->where('model_type', '=', $modelType);
            })->get();
    }

    /**
     * checks if current role authorized to access given node id
     * @param int $nodeId
     * @param string|null $modelType
     * @return OrganizationNode|array|Collection|Model
     * @throws InvalidOrganizationNodeException|Throwable
     */
    public function organizationNode(int $nodeId, ?string $modelType = null): OrganizationNode|array|Collection|Model
    {
        $organizationNodes = $this->organizationNodes(true, $modelType);

        foreach ($organizationNodes as $organizationNode) {
            if ($nodeId == $organizationNode->id) {
                return $organizationNode;
            }
        }
        /*
        if ($organizationNodes->contains(fn($node, $key) => $node->id == $nodeId)) {
            return OrganizationNode::findOrFail($nodeId)->first();
        }
        */
        throw new InvalidOrganizationNodeException();
    }

    /**
     * Checks if tree has given child
     * No permisson check.
     * @param int $rootNodeId
     * @param int $childNodeId
     * @return bool
     * @throws Throwable
     */
    public function descendant(int $rootNodeId, int $childNodeId): bool
    {
        $subTreeRootNode = OrganizationNode::find($rootNodeId);
        throw_unless($subTreeRootNode, new InvalidOrganizationNodeException());

        return OrganizationNode::where('path', 'like', $subTreeRootNode->path . '%')
            ->where('id', '=', $childNodeId)->exists();
    }
}
