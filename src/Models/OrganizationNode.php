<?php

namespace AuroraWebSoftware\AAuth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\OrganizationNode
 *
 * @property int $id
 * @property int $organization_scope_id
 * @property string $name
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string $path
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $assigned_node_count
 * @property-read bool $deletable
 * @property-read OrganizationScope $organization_scope
 *
 * @method static Builder|OrganizationNode newModelQuery()
 * @method static Builder|OrganizationNode newQuery()
 * @method static Builder|OrganizationNode query()
 * @method static Builder|OrganizationNode whereCreatedAt($value)
 * @method static Builder|OrganizationNode whereId($value)
 * @method static Builder|OrganizationNode where($value)
 * @method static Builder|OrganizationNode whereModelId($value)
 * @method static Builder|OrganizationNode whereModelType($value)
 * @method static Builder|OrganizationNode whereName($value)
 * @method static Builder|OrganizationNode whereOrganizationScopeId($value)
 * @method static Builder|OrganizationNode whereParentId($value)
 * @method static Builder|OrganizationNode wherePath($value)
 * @method static Builder|OrganizationNode whereUpdatedAt($value)
 * @method static OrganizationNode find($value)
 * @mixin OrganizationNode
 */
class OrganizationNode extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\AuroraWebSoftware\AAuth\Models\OrganizationNode>> */
    use HasFactory;

    protected $fillable = ['organization_scope_id', 'name', 'model_type', 'model_id', 'path', 'parent_id'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\AuroraWebSoftware\AAuth\Models\OrganizationScope, static>
     */
    public function organization_scope(): BelongsTo
    {
        // todo
        return $this->belongsTo(OrganizationScope::class);
    }

    public function getAssignedNodeCountAttribute(): int
    {
        //todo new attribute syntax
        return DB::table('user_role_organization_node')
            ->where('organization_node_id', $this->id)->count();
    }

    /**
     * @return bool
     */
    public function getDeletableAttribute(): bool
    {
        //todo new attrbiute syntax
        if (OrganizationNode::whereParentId($this->id)->exists()) {
            return false;
        }

        return $this->getAssignedNodeCountAttribute() == 0;
    }

    /**
     * @return Collection<int, OrganizationScope>
     * todo daha güzel fonksiyon ismi bulunmalı
     */
    public function availableScopes(): Collection
    {
        return OrganizationScope::where([
            ['status', 'active'],
            ['level', '>', $this->organization_scope->level],
        ])->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, OrganizationNode>
     */
    public function breadCrumbs(): \Illuminate\Support\Collection
    {
        $pathNodeIds = explode('/', $this->path);
        /* @phpstan-ignore-next-line */
        $breadCrumbs = collect();
        foreach ($pathNodeIds as $pathNodeId) {
            $breadCrumbs->push(OrganizationNode::findOrFail($pathNodeId));
        }

        return $breadCrumbs;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, \AuroraWebSoftware\AAuth\Models\OrganizationNode>
     */
    public function relatedModel(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
    }
}
