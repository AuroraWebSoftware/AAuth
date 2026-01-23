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

    protected ?string $panelId = null;

    /**
     * @throws Throwable
     */
    public function __construct(?AAuthUserContract $user, ?int $roleId, ?string $panelId = null)
    {
        throw_unless($user, new AuthenticationException());
        throw_unless($roleId, new MissingRoleException());

        throw_if(
            $user->roles()->where('roles.id', '=', $roleId)->count() < 1,
            new UserHasNoAssignedRoleException()
        );

        $this->user = $user;
        $this->panelId = $panelId;

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

    /**
     * Create AAuth instance for specific Filament panel
     *
     * @param AAuthUserContract $user
     * @param int $roleId
     * @param string $panelId
     * @return self
     * @throws Throwable
     */
    public static function forPanel(AAuthUserContract $user, int $roleId, string $panelId): self
    {
        return new self($user, $roleId, $panelId);
    }

    /**
     * Create AAuth instance for current Filament panel (auto-detect)
     *
     * @param AAuthUserContract $user
     * @param int $roleId
     * @return self
     * @throws Throwable
     */
    public static function forCurrentPanel(AAuthUserContract $user, int $roleId): self
    {
        $panelId = self::detectCurrentPanelId();

        return new self($user, $roleId, $panelId);
    }

    /**
     * Detect current Filament panel ID
     *
     * @return string|null
     */
    public static function detectCurrentPanelId(): ?string
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            return null;
        }

        try {
            return \Filament\Facades\Filament::getCurrentPanel()?->getId();
        } catch (\Throwable $e) {
            return null;
        }
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
        if (config('aauth-advanced.cache.enabled', false)) {
            return $this->getCachedSwitchableRoles();
        }

        return $this->loadSwitchableRoles();
    }

    /**
     * Get switchable roles for specific Filament panel
     *
     * @param string $panelId
     * @return Collection<int, Role>
     */
    public function switchableRolesForPanel(string $panelId): Collection
    {
        $query = Role::where('uro.user_id', '=', $this->user->id)
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct();

        if ($this->hasRolesPanelIdColumn()) {
            $query->where(function ($q) use ($panelId) {
                $q->where('panel_id', $panelId)->orWhereNull('panel_id');
            });
        }

        // @phpstan-ignore-next-line
        return $query->select('roles.id', 'name')->get();
    }

    /**
     * Get switchable roles for current Filament panel
     *
     * @return Collection<int, Role>
     */
    public function switchableRolesForCurrentPanel(): Collection
    {
        $panelId = $this->panelId ?? self::detectCurrentPanelId();

        if (! $panelId) {
            return $this->switchableRoles();
        }

        return $this->switchableRolesForPanel($panelId);
    }

    /**
     * Get role's panel ID from database
     *
     * @return string|null
     */
    public function getPanelId(): ?string
    {
        if (! $this->hasRolesPanelIdColumn()) {
            return null;
        }

        return $this->role->panel_id;
    }

    /**
     * Get current Filament panel context
     *
     * @return string|null
     */
    public function getCurrentPanel(): ?string
    {
        return $this->panelId;
    }

    /**
     * Check if in specific Filament panel
     *
     * @param string $panelId
     * @return bool
     */
    public function isInPanel(string $panelId): bool
    {
        return $this->panelId === $panelId;
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
     * Get switchable roles for specific Filament panel (static)
     *
     * @param int $userId
     * @param string $panelId
     * @return Collection<int, Role>
     */
    public static function switchableRolesForPanelStatic(int $userId, string $panelId): Collection
    {
        $query = Role::where('uro.user_id', '=', $userId)
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct();

        if (Schema::hasColumn('roles', 'panel_id')) {
            $query->where(function ($q) use ($panelId) {
                $q->where('panel_id', $panelId)->orWhereNull('panel_id');
            });
        }

        // @phpstan-ignore-next-line
        return $query->select('roles.id', 'name')->get();
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
     * Check if panel_id column exists in roles table (for Filament support)
     *
     * @return bool
     */
    protected function hasRolesPanelIdColumn(): bool
    {
        static $hasPanelId = null;

        if ($hasPanelId === null) {
            $hasPanelId = Schema::hasColumn('roles', 'panel_id');
        }

        return $hasPanelId;
    }

    /**
     * Get cached role with permissions and ABAC rules
     *
     * @param int $roleId
     * @return Role|null
     */
    protected function getCachedRole(int $roleId): ?Role
    {
        $prefix = config('aauth-advanced.cache.prefix', 'aauth');
        $ttl = config('aauth-advanced.cache.ttl', 3600);
        $store = config('aauth-advanced.cache.store');

        $cacheKey = "{$prefix}:role:{$roleId}";

        if ($this->panelId) {
            $cacheKey .= ":panel:{$this->panelId}";
        }

        $cache = $store ? Cache::store($store) : Cache::store();

        return $cache->remember($cacheKey, $ttl, function () use ($roleId) {
            return $this->loadRole($roleId);
        });
    }

    /**
     * Load role from database with permissions and ABAC rules
     *
     * @param int $roleId
     * @return Role|null
     */
    protected function loadRole(int $roleId): ?Role
    {
        $query = Role::with(['rolePermissions', 'abacRules']);

        if ($this->panelId && $this->hasRolesPanelIdColumn()) {
            $query->where(function ($q) {
                $q->where('panel_id', $this->panelId)->orWhereNull('panel_id');
            });
        }

        return $query->find($roleId);
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
            ->leftJoin('user_role_organization_node as uro', 'uro.role_id', '=', 'roles.id')
            ->distinct()
            ->select('roles.id', 'name')->get();
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

        if (! array_key_exists($permission, $context['permissions'])) {
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
        return $permission . ':' . md5(json_encode($arguments));
    }

    protected function validateParameters(array $roleParameters, array $arguments): bool
    {
        foreach ($roleParameters as $paramName => $roleValue) {
            $argIndex = array_search($paramName, array_keys($roleParameters));

            if (! isset($arguments[$argIndex])) {
                continue;
            }

            $runtimeValue = $arguments[$argIndex];

            if (is_int($roleValue) && is_numeric($runtimeValue)) {
                if ($runtimeValue > $roleValue) {
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
     * Get accessible organization nodes with depth and scope filtering
     *
     * @param int|null $minDepthFromRoot Minimum depth from root (inclusive, 0-based)
     * @param int|null $maxDepthFromRoot Maximum depth from root (inclusive, 0-based)
     * @param string|null $scopeName Organization scope name filter
     * @param int|null $scopeLevel Organization scope level filter
     * @param bool $includeRootNode Include root nodes in results
     * @param string|null $modelType Filter by model type
     * @return \Illuminate\Support\Collection
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
            // Depth filtering using path length calculation
            // Depth = (number of slashes in path) - 1
            // Examples: "/" = depth 0, "/1/" = depth 1, "/1/3/" = depth 2
            ->when($minDepthFromRoot !== null || $maxDepthFromRoot !== null, function ($query) use ($minDepthFromRoot, $maxDepthFromRoot) {
                if ($minDepthFromRoot !== null) {
                    // MySQL and PostgreSQL compatible
                    $query->whereRaw('(LENGTH(path) - LENGTH(REPLACE(path, ?, ?))) - 1 >= ?', ['/', '', $minDepthFromRoot]);
                }

                if ($maxDepthFromRoot !== null) {
                    $query->whereRaw('(LENGTH(path) - LENGTH(REPLACE(path, ?, ?))) - 1 <= ?', ['/', '', $maxDepthFromRoot]);
                }
            })
            // Scope name filtering
            ->when($scopeName !== null, function ($query) use ($scopeName) {
                $query->whereHas('organization_scope', function ($q) use ($scopeName) {
                    $q->where('name', $scopeName);
                });
            })
            // Scope level filtering
            ->when($scopeLevel !== null, function ($query) use ($scopeLevel) {
                $query->whereHas('organization_scope', function ($q) use ($scopeLevel) {
                    $q->where('level', $scopeLevel);
                });
            })
            // Model type filtering
            ->when($modelType !== null, function ($query) use ($modelType) {
                $query->where('model_type', '=', $modelType);
            })
            ->get();
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
