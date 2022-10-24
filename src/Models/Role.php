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
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $status
 * @property OrganizationNode $organizationNode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $organization_scope_id
 * @method static find($role_id) : Role
 */
class Role extends Model
{
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
     * @return BelongsTo<OrganizationScope, Role>
     */
    public function organization_scope(): BelongsTo
    {
        return $this->belongsTo(OrganizationScope::class);
    }

    /**
     * @return BelongsToMany<OrganizationNode>
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
