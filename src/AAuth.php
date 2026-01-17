<?php

namespace AuroraWebSoftware\AAuth;

use AuroraWebSoftware\AAuth\Contracts\AAuthUserContract;
use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException;
use AuroraWebSoftware\AAuth\Exceptions\MissingRoleException;
use AuroraWebSoftware\AAuth\Exceptions\UserHasNoAssignedRoleException;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RoleModelAbacRule;
use AuroraWebSoftware\AAuth\Models\RolePermission;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

class AAuth
{
    private const CONTEXT_KEY = 'aauth_context';

    public AAuthUserContract $user;

    public Role $role;

    public ?array $organizationNodeIds;

    protected array $requestCache = [];

    /**
     * @throws Throwable
     */
    public function __construct(?AAuthUserContract $user, ?int $roleId)
    {
        throw_unless($user, new AuthenticationException());
        throw_unless($roleId, new MissingRoleException());

        throw_if(
            $user->roles()->where('roles.id', '=', $roleId)->count() < 1,
            new UserHasNoAssignedRoleException()
        );

        $this->user = $user;
        $this->role = Role::with(['rolePermissions', 'abacRules'])->find($roleId);

        throw_unless($this->role, new MissingRoleException());

        $this->organizationNodeIds = DB::table('user_role_organization_node')
            ->where('user_id', '=', $user->id)
            ->where('role_id', '=', $roleId)
            ->pluck('organization_node_id')->toArray();

        $this->loadAndCacheContext();
    }

    /**
     * @return Role|null
     */
    public function currentRole(): ?Role
    {
        // todo unit test
        return $this->role;
    }

    /**
     * @return array|Collection<int, Role>|\Illuminate\Support\Collection<int, Role>
     */
    public function switchableRoles(): array|Collection|\Illuminate\Support\Collection
    {
        // @phpstan-ignore-next-line
        return Role::where('uro.user_id', '=', $this->user->id)
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct()
            ->select('roles.id', 'name')->get();
    }

    /**
     * @param int $userId
     * @return array|Collection<int, Role>|\Illuminate\Support\Collection<int, Role>
     */
    public static function switchableRolesStatic(int $userId): array|Collection|\Illuminate\Support\Collection
    {
        // todo test'i yazılacak
        return Role::where('uro.user_id', '=', $userId)
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct()
            ->select('roles.id', 'name')->get();
    }

    /**
     * Role's all permissions
     *
     * @return array
     */
    public function permissions(): array
    {
        return Role::where('roles.id', '=', $this->role->id)
            ->leftJoin('role_permission as rp', 'rp.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
    }

    /**
     * Get organization permissions for current role
     * Defensive: supports both old 'type' column and new organization_scope_id approach
     *
     * @return array
     */
    public function organizationPermissions(): array
    {
        $query = Role::where('roles.id', '=', $this->role->id);

        // Defensive: check if old 'type' column exists
        if ($this->hasRolesTypeColumn()) {
            $query->where('type', '=', 'organization');
        } else {
            $query->whereNotNull('organization_scope_id');
        }

        return $query
            ->leftJoin('role_permission as rp', 'rp.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
    }

    /**
     * Get system permissions for current role
     * Defensive: supports both old 'type' column and new organization_scope_id approach
     *
     * @return array
     */
    public function systemPermissions(): array
    {
        $query = Role::where('roles.id', '=', $this->role->id);

        // Defensive: check if old 'type' column exists
        if ($this->hasRolesTypeColumn()) {
            $query->where('type', '=', 'system');
        } else {
            $query->whereNull('organization_scope_id');
        }

        return $query
            ->leftJoin('role_permission as rp', 'rp.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
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
     * @param string $permission
     * @param mixed ...$arguments
     * @return bool
     */
    public function can(string $permission, mixed ...$arguments): bool
    {
        $cacheKey = $this->getPermissionCacheKey($permission, $arguments);

        if (isset($this->requestCache[$cacheKey])) {
            return $this->requestCache[$cacheKey];
        }

        $context = $this->getAuthContext();

        if ($context['is_super_admin']) {
            $this->requestCache[$cacheKey] = true;
            return true;
        }

        if (!isset($context['permissions'][$permission])) {
            $this->requestCache[$cacheKey] = false;
            return false;
        }

        if (empty($arguments)) {
            $this->requestCache[$cacheKey] = true;
            return true;
        }

        $roleParameters = $context['permissions'][$permission];
        $result = empty($roleParameters) || $this->validateParameters($roleParameters, $arguments);

        $this->requestCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        $context = $this->getAuthContext();
        return $context['is_super_admin'];
    }

    protected function loadAndCacheContext(): void
    {
        $permissions = [];
        foreach ($this->role->rolePermissions as $rp) {
            $permissions[$rp->permission] = $rp->parameters;
        }

        $abacRules = [];
        foreach ($this->role->abacRules as $rule) {
            $abacRules[$rule->model_type] = $rule->rules_json;
        }

        $isSuperAdmin = false;
        if (config('aauth.super_admin.enabled', false)) {
            $column = config('aauth.super_admin.column', 'is_super_admin');
            $isSuperAdmin = (bool) ($this->user->{$column} ?? false);
        }

        $context = [
            'user_id' => $this->user->id,
            'role_id' => $this->role->id,
            'role_name' => $this->role->name,
            'organization_node_ids' => $this->organizationNodeIds,
            'permissions' => $permissions,
            'abac_rules' => $abacRules,
            'is_super_admin' => $isSuperAdmin,
        ];

        Context::addHidden(self::CONTEXT_KEY, $context);
    }

    protected function getAuthContext(): array
    {
        $context = Context::getHidden(self::CONTEXT_KEY);

        if ($context === null) {
            $this->loadAndCacheContext();
            $context = Context::getHidden(self::CONTEXT_KEY);
        }

        return $context;
    }

    public function clearContext(): void
    {
        $this->requestCache = [];
        Context::forgetHidden(self::CONTEXT_KEY);
    }

    protected function getPermissionCacheKey(string $permission, array $arguments): string
    {
        return $permission . ':' . md5(serialize($arguments));
    }

    protected function validateParameters(array $roleParameters, array $arguments): bool
    {
        foreach ($roleParameters as $paramName => $roleValue) {
            $argIndex = array_search($paramName, array_keys($roleParameters));

            if (!isset($arguments[$argIndex])) {
                continue;
            }

            $runtimeValue = $arguments[$argIndex];

            if (is_int($roleValue) && is_numeric($runtimeValue)) {
                if ($runtimeValue > $roleValue) {
                    return false;
                }
            } elseif (is_array($roleValue)) {
                if (!in_array($runtimeValue, $roleValue)) {
                    return false;
                }
            } elseif (is_bool($roleValue)) {
                if ((bool) $runtimeValue !== $roleValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @deprecated Use getAuthContext() instead
     * @return array<string, array|null>
     */
    protected function getPermissionsWithParameters(): array
    {
        $context = $this->getAuthContext();
        return $context['permissions'];
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
     *
     * @param bool $includeRootNode
     * @param string|null $modelType
     * @return \Illuminate\Support\Collection<int, OrganizationNode>
     *
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

                /**
                 * @phpstan-ignore-next-line
                 */
                $query->orWhere('path', 'like', $rootNode->path . '/%');

                if ($includeRootNode) {
                    /**
                     * @phpstan-ignore-next-line
                     */
                    $query->orWhere('path', $rootNode->path);
                }

            }
        })
            ->when($modelType !== null, function ($query) use ($modelType) {
                return $query->where('model_type', '=', $modelType);
            })->get();
    }

    /**
     * @param bool $includeRootNode
     * @param string|null $modelType
     * @return OrganizationNode|Builder
     * @throws Throwable
     */
    public function organizationNodesQuery(bool $includeRootNode = false, ?string $modelType = null): OrganizationNode|Builder
    {
        $rootNodes = OrganizationNode::whereIn('id', $this->organizationNodeIds)->get();
        throw_unless($rootNodes->isNotEmpty(), new InvalidOrganizationNodeException());

        return OrganizationNode::where(function ($query) use ($rootNodes, $includeRootNode) {
            foreach ($rootNodes as $rootNode) {
                $query->orWhere('path', 'like', $rootNode->path . '/%');

                if ($includeRootNode) {
                    $query->orWhere('path', '=', $rootNode->path);
                }
            }
        })->when($modelType !== null, function ($query) use ($modelType) {
            return $query->where('model_type', '=', $modelType);
        });
    }

    /**
     * checks if current role authorized to access given node id
     *
     * @param int $nodeId
     * @param string|null $modelType
     * @return OrganizationNode
     *
     * @throws InvalidOrganizationNodeException|Throwable
     */
    public function organizationNode(int $nodeId, ?string $modelType = null): OrganizationNode
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
     * @return array|null
     */
    public function organizationNodeIds(): ?array
    {
        return $this->organizationNodeIds;
    }

    /**
     * Checks if tree has given child
     * No permission check.
     *
     * @param int $rootNodeId
     * @param int $childNodeId
     * @return bool
     *
     * @throws Throwable
     */
    public function descendant(int $rootNodeId, int $childNodeId): bool
    {
        $subTreeRootNode = OrganizationNode::find($rootNodeId);
        throw_unless($subTreeRootNode, new InvalidOrganizationNodeException());

        return OrganizationNode::where('path', 'like', $subTreeRootNode->path . '%')
            ->where('id', '=', $childNodeId)->exists();
    }

    /**
     * @param string $modelType
     * @return array|null
     */
    public function ABACRules(string $modelType): ?array
    {
        return RoleModelAbacRule::where('role_id', '=', $this->role->id)
            ->where('model_type', '=', $modelType)
            ->first()?->rules_json;
    }
}
