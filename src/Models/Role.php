<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\AuroraWebSoftware\AAuth\Models\Role>> */
    use HasFactory;

    protected $fillable = ['organization_scope_id', 'type', 'name', 'status'];

    /**
     * Get permissions as array (legacy method - backward compatible)
     *
     * @return array
     */
    public function permissions(): array
    {
        return $this
            ->join('role_permission', 'role_permission.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
    }

    /**
     * Get role permissions as HasMany relationship (v2)
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\AuroraWebSoftware\AAuth\Models\RolePermission, \AuroraWebSoftware\AAuth\Models\Role>
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\AuroraWebSoftware\AAuth\Models\RoleModelAbacRule, \AuroraWebSoftware\AAuth\Models\Role>
     */
    public function abacRules(): HasMany
    {
        return $this->hasMany(RoleModelAbacRule::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\AuroraWebSoftware\AAuth\Models\OrganizationScope, \AuroraWebSoftware\AAuth\Models\Role>
     */
    public function organization_scope(): BelongsTo
    {
        return $this->belongsTo(OrganizationScope::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\AuroraWebSoftware\AAuth\Models\OrganizationNode, \AuroraWebSoftware\AAuth\Models\Role, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function organization_nodes(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationNode::class, 'user_role_organization_node');
    }

    /**
     * Give a permission to this role
     *
     * @param string $permission
     * @param array|null $parameters
     * @return \AuroraWebSoftware\AAuth\Models\RolePermission
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
     *
     * @param string $permission
     * @return bool
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
     * @param array $permissions Array of permission strings or ['permission' => 'params'] pairs
     * @return void
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
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return RolePermission::where('role_id', $this->id)
            ->where('permission', $permission)
            ->exists();
    }

    /**
     * @return int
     */
    public function getAssignedUserCountAttribute(): int
    {
        // new attribute syntax
        return DB::table('user_role_organization_node')
            ->where('role_id', $this->id)->groupBy('user_id')->count();
    }

    /**
     * @return bool
     */
    public function getDeletableAttribute(): bool
    {
        // new attribute syntax
        return $this->getAssignedUserCountAttribute() == 0;
    }
}
