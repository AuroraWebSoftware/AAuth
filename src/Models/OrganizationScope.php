<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * AuroraWebSoftware\AAuth\Models\OrganizationScope
 *
 * @property int $id
 * @property string $type
 * @property string $name
 * @property int $level
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @method static Builder|OrganizationScope whereLevel($value)
 *
 * @property-read bool $deletable
 * @property-read bool $is_active
 * @property-read int $node_count
 * @property-read string $status_color
 * @property-read Collection<int, OrganizationNode>|OrganizationNode[] $organization_nodes
 * @property-read int|null $organization_nodes_count
 */
class OrganizationScope extends Model
{
    /** @use HasFactory<Factory<OrganizationScope>> */
    use HasFactory;

    protected $primaryKey = 'id';

    protected $fillable = ['name', 'level', 'status'];

    public function getNodeCountAttribute(): int
    {
        // todo new attribute syntax
        return OrganizationNode::whereOrganizationScopeId($this->id)->count();
    }

    public function getDeletableAttribute(): bool
    {
        // todo new attribute syntax
        return $this->getNodeCountAttribute() == 0;
    }

    /**
     * @return HasMany<OrganizationNode, $this>
     */
    public function organization_nodes(): HasMany
    {
        return $this->hasMany(OrganizationNode::class);
    }
}
