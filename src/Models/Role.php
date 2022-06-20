<?php

namespace Aurora\AAuth\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;


/**
 * App\Models\Permission
 *
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null $organization_scope_id
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereOrganizationScopeId($value)
 * @property-read Collection $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection|\App\Models\User[] $users
 * @property-read int|null $users_count
 * @property-read OrganizationNode $organization_node
 * @property-read int $assigned_user_count
 * @property-read bool $deletable
 * @property-read string $status_color
 * @property-read int|null $organization_node_count
 * @property-read \App\Models\OrganizationScope|null $organization_scope
 * @property-read Collection|\App\Models\OrganizationNode[] $organization_nodes
 * @property-read int|null $organization_nodes_count
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
     * @return BelongsTo
     */
    public function organization_scope(): BelongsTo
    {
        return $this->belongsTo(OrganizationScope::class);
    }

    /**
     * @return BelongsToMany
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
        // new attrbiute syntax
        return DB::table('user_role_organization_node')
            ->where('role_id', $this->id)->groupBy('user_id')->count();
    }

    /**
     * @return bool
     */
    public function getDeletableAttribute(): bool
    {
        // new attrbiute syntax
        return $this->getAssignedUserCountAttribute() == 0;
    }

}
