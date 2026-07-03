<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AuroraWebSoftware\AAuth\Models\Role
 *
 * @property-read int $id
 * @property string|null $type
 * @property string $name
 * @property string $status
 * @property OrganizationNode $organizationNode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $organization_scope_id
 * @property-read string|null $permission
 *
 * @method static Role find($role_id)
 */
class Role extends Model
{
    /** @use HasFactory<Factory<Role>> */
    use HasFactory;

    protected $fillable = ['organization_scope_id', 'type', 'name', 'status'];

    /**
     * Get permissions as array (legacy method - backward compatible)
     */
    public function permissions(): array
    {
        return RolePermission::where('role_id', $this->id)
            ->pluck('permission')->toArray();
    }

    /**
     * Get role permissions as HasMany relationship (v2)
     *
     * @return HasMany<RolePermission, Role>
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * @return HasMany<RoleModelAbacRule, Role>
     */
    public function abacRules(): HasMany
    {
        return $this->hasMany(RoleModelAbacRule::class);
    }

    /**
     * @return BelongsTo<OrganizationScope, Role>
     */
    public function organization_scope(): BelongsTo
    {
        return $this->belongsTo(OrganizationScope::class);
    }

    /**
     * @return BelongsToMany<OrganizationNode, Role, Pivot>
     */
    public function organization_nodes(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationNode::class, 'user_role_organization_node');
    }

    /**
     * Give a permission to this role
     */
    public function givePermission(string $permission, ?array $parameters = null): RolePermission
    {
        return RolePermission::updateOrCreate(
            [
                'role_id' => $this->id,
                'permission' => $permission,
            ],
            [
                'parameters' => $parameters,
            ]
        );
    }

    /**
     * Remove a permission from this role
     */
    public function removePermission(string $permission): bool
    {
        return RolePermission::where('role_id', $this->id)
            ->where('permission', $permission)
            ->delete() > 0;
    }

    /**
     * Sync permissions for this role
     *
     * @param  array  $permissions  Array of permission strings or ['permission' => 'params'] pairs
     */
    public function syncPermissions(array $permissions): void
    {
        // Delete all existing permissions
        RolePermission::where('role_id', $this->id)->delete();

        // Add new permissions
        foreach ($permissions as $key => $value) {
            if (is_string($key)) {
                // ['permission' => ['param' => 'value']] format
                $this->givePermission($key, $value);
            } else {
                // ['permission1', 'permission2'] format
                $this->givePermission($value);
            }
        }
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return RolePermission::where('role_id', $this->id)
            ->where('permission', $permission)
            ->exists();
    }

    public function getAssignedUserCountAttribute(): int
    {
        // Distinct users assigned this role (a user may hold it at multiple nodes).
        return DB::table('user_role_organization_node')
            ->where('role_id', $this->id)->distinct('user_id')->count('user_id');
    }

    public function getDeletableAttribute(): bool
    {
        // new attribute syntax
        return $this->getAssignedUserCountAttribute() == 0;
    }
}
