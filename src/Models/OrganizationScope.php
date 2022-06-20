<?php

namespace Aurora\AAuth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * App\Models\OrganizationScope
 *
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder|Permission newModelQuery()
 * @method static Builder|Permission newQuery()
 * @method static Builder|Permission query()
 * @method static Builder|Permission whereCreatedAt($value)
 * @method static Builder|Permission whereId($value)
 * @method static Builder|Permission whereName($value)
 * @method static Builder|Permission whereStatus($value)
 * @method static Builder|Permission whereType($value)
 * @method static Builder|Permission whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int $level
 * @method static Builder|OrganizationScope whereLevel($value)
 * @property-read bool $deletable
 * @property-read bool $is_active
 * @property-read int $node_count
 * @property-read string $status_color
 * @property-read Collection|\App\Models\OrganizationNode[] $organization_nodes
 * @property-read int|null $organization_nodes_count
 */
class OrganizationScope extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $fillable = ['name', 'level', 'status'];


    /**
     * @return int
     */
    public function getNodeCountAttribute(): int
    {
        // todo new attribute syntax
        return OrganizationNode::whereOrganizationScopeId($this->id)->count();
    }

    /**
     * @return bool
     */
    public function getDeletableAttribute(): bool
    {
        // todo new attribute syntax
        return $this->getNodeCountAttribute() == 0;
    }

    /**
     * @return HasMany
     */
    public function organization_nodes(): HasMany
    {
        return $this->hasMany(OrganizationNode::class);
    }

}
