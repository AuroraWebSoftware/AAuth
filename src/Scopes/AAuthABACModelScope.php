<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use Illuminate\Database\Eloquent\Builder;

class AAuthABACModelScope implements \Illuminate\Database\Eloquent\Scope
{
    public function apply(Builder $builder, \Illuminate\Database\Eloquent\Model $model)
    {
        // todo
        // $organizationNodeIds = AAuth::organizationNodes(true, $model->id)->pluck('id');
        // $builder->whereIn('id', $organizationNodeIds);
    }
}
