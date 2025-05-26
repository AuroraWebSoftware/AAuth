<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AuroraWebSoftware\AAuth\Models\Role
 *
 * @property-read int $id
 * @property string $type
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
     * @return array
     */
    public function permissions(): array
    {
        return $this
            ->join('role_permission', 'role_permission.role_id', '=', 'roles.id')
            ->pluck('permission')->toArray();
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
