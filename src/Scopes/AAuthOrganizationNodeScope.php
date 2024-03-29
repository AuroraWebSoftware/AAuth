<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AAuthOrganizationNodeScope implements \Illuminate\Database\Eloquent\Scope
{
    /**
     * @param  Builder  $builder
     * @param  Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationNodeModelIds = AAuth::organizationNodes(true, $model::getModelType())->pluck('model_id');
        $builder->whereIn('id', $organizationNodeModelIds);
    }
}
