<?php

namespace AuroraWebSoftware\AAuth;

use AuroraWebSoftware\AAuth\Contracts\AAuthUserContract;
use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException;
use AuroraWebSoftware\AAuth\Exceptions\MissingRoleException;
use AuroraWebSoftware\AAuth\Exceptions\UserHasNoAssignedRoleException;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RoleModelAbacRule;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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

        // Only an ACTIVE assigned role may be selected — deactivateRole() is an
        // effective kill switch on the very next request.
        throw_if(
            $user->roles()->where('roles.id', '=', $roleId)->where('status', '=', 'active')->count() < 1,
            new UserHasNoAssignedRoleException()
        );

        $this->user = $user;

        $this->role = config('aauth-advanced.cache.enabled', false)
            ? $this->getCachedRole($roleId)
            : $this->loadRole($roleId);

        throw_unless($this->role, new MissingRoleException());

        $this->organizationNodeIds = DB::table('user_role_organization_node')
            ->where('user_id', '=', $user->id)
            ->where('role_id', '=', $roleId)
            ->pluck('organization_node_id')->toArray();

        $this->loadAndCacheContext();
    }

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
        if (config('aauth-advanced.cache.enabled', false)) {
            return $this->getCachedSwitchableRoles();
        }

        return $this->loadSwitchableRoles();
    }

    /**
     * @return array|Collection<int, Role>|\Illuminate\Support\Collection<int, Role>
     */
    public static function switchableRolesStatic(int $userId): array|Collection|\Illuminate\Support\Collection
    {
        // todo test'i yazılacak
        return Role::where('uro.user_id', '=', $userId)
            ->where('status', '=', 'active')
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct()
            ->select('roles.id', 'name')->get();
    }

    /**
     * Role's all permissions
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
     * Get cached role with permissions and ABAC rules
     */
    protected function getCachedRole(int $roleId): ?Role
    {
        $prefix = config('aauth-advanced.cache.prefix', 'aauth');
        $ttl = config('aauth-advanced.cache.ttl', 3600);
        $store = config('aauth-advanced.cache.store');

        $cacheKey = "{$prefix}:role:{$roleId}";

        $cache = $store ? Cache::store($store) : Cache::store();

        return $cache->remember($cacheKey, $ttl, function () use ($roleId) {
            return $this->loadRole($roleId);
        });
    }

    /**
     * Load role from database with permissions and ABAC rules
     */
    protected function loadRole(int $roleId): ?Role
    {
        return Role::with(['rolePermissions', 'abacRules'])->find($roleId);
    }

    /**
     * Get cached switchable roles for current user
     *
     * @return Collection<int, Role>
     */
    protected function getCachedSwitchableRoles(): Collection
    {
        $prefix = config('aauth-advanced.cache.prefix', 'aauth');
        $ttl = config('aauth-advanced.cache.ttl', 3600);
        $store = config('aauth-advanced.cache.store');

        $cacheKey = "{$prefix}:user:{$this->user->id}:switchable_roles";

        $cache = $store ? Cache::store($store) : Cache::store();

        return $cache->remember($cacheKey, $ttl, function () {
            return $this->loadSwitchableRoles();
        });
    }

    /**
     * Load switchable roles from database
     *
     * @return Collection<int, Role>
     */
    protected function loadSwitchableRoles(): Collection
    {
        // @phpstan-ignore-next-line
        return Role::where('uro.user_id', '=', $this->user->id)
            ->where('status', '=', 'active')
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct()
            ->select('roles.id', 'name')->get();
    }

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

        if (! array_key_exists($permission, $context['permissions'])) {
            $this->requestCache[$cacheKey] = false;

            return false;
        }

        if (empty($arguments)) {
            // Fail closed: a permission that declares parameter constraints cannot be
            // granted without the runtime value(s) needed to check them.
            $result = empty($context['permissions'][$permission]);
            $this->requestCache[$cacheKey] = $result;

            return $result;
        }

        $roleParameters = $context['permissions'][$permission];
        $result = empty($roleParameters) || $this->validateParameters($roleParameters, $arguments);

        $this->requestCache[$cacheKey] = $result;

        return $result;
    }

    public function isSuperAdmin(): bool
    {
        $context = $this->getAuthContext();

        return $context['is_super_admin'];
    }

    protected function loadAndCacheContext(): void
    {
        // Refresh relationships from database to get latest data
        $this->role->load(['rolePermissions', 'abacRules']);

        $permissions = [];
        foreach ($this->role->rolePermissions as $rp) {
            $permissions[$rp->permission] = $rp->parameters;
        }

        $abacRules = [];
        foreach ($this->role->abacRules as $rule) {
            $abacRules[$rule->model_type] = $rule->rules_json;
        }

        $isSuperAdmin = false;
        if (config('aauth-advanced.super_admin.enabled', false)) {
            $column = config('aauth-advanced.super_admin.column', 'is_super_admin');
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
            // Refresh role to get latest data in case of mid-request updates
            $this->role->refresh();
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
        return $permission.':'.md5(json_encode($arguments) ?: '');
    }

    protected function validateParameters(array $roleParameters, array $arguments): bool
    {
        // Positional matching: the Nth declared parameter is checked against the Nth
        // runtime argument. Fail closed on any missing/mismatched/unknown constraint.
        $argIndex = 0;
        foreach ($roleParameters as $roleValue) {
            if (! array_key_exists($argIndex, $arguments)) {
                return false;
            }

            $runtimeValue = $arguments[$argIndex];
            $argIndex++;

            if (is_int($roleValue)) {
                // Numeric upper bound; a non-numeric runtime value cannot satisfy it.
                if (! is_numeric($runtimeValue) || $runtimeValue > $roleValue) {
                    return false;
                }
            } elseif (is_array($roleValue)) {
                if (! in_array($runtimeValue, $roleValue)) {
                    return false;
                }
            } elseif (is_bool($roleValue)) {
                if ((bool) $runtimeValue !== $roleValue) {
                    return false;
                }
            } elseif (is_string($roleValue)) {
                if ((string) $runtimeValue !== $roleValue) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * @deprecated Use getAuthContext() instead
     *
     * @return array<string, array|null>
     */
    protected function getPermissionsWithParameters(): array
    {
        $context = $this->getAuthContext();

        return $context['permissions'];
    }

    /**
     * @param  array<int, mixed>  $arguments  parametric permission values, forwarded to can()
     */
    public function passOrAbort(string $permission, string $message = 'No Permission', array $arguments = []): void
    {
        if (! $this->can($permission, ...$arguments)) {
            abort(ResponseAlias::HTTP_UNAUTHORIZED, $message);
        }
    }

    /**
     * Returns user's current role's authorized organization nodes
     * if model type is given, returns only this model typed nodes.
     *
     * @return \Illuminate\Support\Collection<int, OrganizationNode>
     *
     * @throws Throwable
     */
    public function organizationNodes(bool $includeRootNode = false, ?string $modelType = null): \Illuminate\Support\Collection
    {
        // Delegate to the single empty-guarded builder: fixes the N+1 (per-id find())
        // and the empty-scope over-exposure (a role with no nodes must return ZERO rows).
        return $this->organizationNodesQuery($includeRootNode, $modelType)->get();
    }

    /**
     * @return Builder<OrganizationNode>|OrganizationNode
     *
     * @throws Throwable
     */
    public function organizationNodesQuery(bool $includeRootNode = false, ?string $modelType = null): OrganizationNode|Builder
    {
        $rootNodes = OrganizationNode::whereIn('id', $this->organizationNodeIds)->get();

        return OrganizationNode::where(function ($query) use ($rootNodes, $includeRootNode) {
            // Fail closed: a role with no accessible nodes matches ZERO rows,
            // never falling through to an unconstrained (whole-table) result.
            if ($rootNodes->isEmpty()) {
                $query->whereRaw('1 = 0');

                return;
            }

            foreach ($rootNodes as $rootNode) {
                $query->orWhere('path', 'like', $rootNode->path.'/%');

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

    public function organizationNodeIds(): ?array
    {
        return $this->organizationNodeIds;
    }

    /**
     * Get accessible organization nodes with depth and scope filtering
     *
     * @param  int|null  $minDepthFromRoot  Minimum depth from root (inclusive, 0-based)
     * @param  int|null  $maxDepthFromRoot  Maximum depth from root (inclusive, 0-based)
     * @param  string|null  $scopeName  Organization scope name filter
     * @param  int|null  $scopeLevel  Organization scope level filter
     * @param  bool  $includeRootNode  Include root nodes in results
     * @param  string|null  $modelType  Filter by model type
     *
     * @throws Throwable
     */
    public function getAccessibleOrganizationNodes(
        ?int $minDepthFromRoot = null,
        ?int $maxDepthFromRoot = null,
        ?string $scopeName = null,
        ?int $scopeLevel = null,
        bool $includeRootNode = false,
        ?string $modelType = null
    ): \Illuminate\Support\Collection {
        // Reuse the single empty-guarded subtree builder (it already fails closed on an
        // empty node set and applies the model-type filter), then layer depth/scope on top.
        return $this->organizationNodesQuery($includeRootNode, $modelType)
            // Depth = (number of '/' in path) - 1.
            ->when($minDepthFromRoot !== null || $maxDepthFromRoot !== null, function ($query) use ($minDepthFromRoot, $maxDepthFromRoot) {
                if ($minDepthFromRoot !== null) {
                    $query->whereRaw('(LENGTH(path) - LENGTH(REPLACE(path, ?, ?))) - 1 >= ?', ['/', '', $minDepthFromRoot]);
                }

                if ($maxDepthFromRoot !== null) {
                    $query->whereRaw('(LENGTH(path) - LENGTH(REPLACE(path, ?, ?))) - 1 <= ?', ['/', '', $maxDepthFromRoot]);
                }
            })
            ->when($scopeName !== null, function ($query) use ($scopeName) {
                $query->whereHas('organization_scope', function ($q) use ($scopeName) {
                    $q->where('name', $scopeName);
                });
            })
            ->when($scopeLevel !== null, function ($query) use ($scopeLevel) {
                $query->whereHas('organization_scope', function ($q) use ($scopeLevel) {
                    $q->where('level', $scopeLevel);
                });
            })
            ->get();
    }

    /**
     * Checks if tree has given child
     * No permission check.
     *
     *
     * @throws Throwable
     */
    public function descendant(int $rootNodeId, int $childNodeId): bool
    {
        $subTreeRootNode = OrganizationNode::find($rootNodeId);
        throw_unless($subTreeRootNode, new InvalidOrganizationNodeException());

        // Anchored to the '/' separator so root '1' does not match sibling '10'/'1/3'→'1/30'.
        return OrganizationNode::where('id', '=', $childNodeId)
            ->where(function ($query) use ($subTreeRootNode) {
                $query->where('path', '=', $subTreeRootNode->path)
                    ->orWhere('path', 'like', $subTreeRootNode->path.'/%');
            })->exists();
    }

    public function ABACRules(string $modelType): ?array
    {
        return RoleModelAbacRule::where('role_id', '=', $this->role->id)
            ->where('model_type', '=', $modelType)
            ->first()?->rules_json;
    }
}
