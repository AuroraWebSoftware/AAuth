<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AAuthABACModelScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // todo
        // $organizationNodeIds = AAuth::organizationNodes(true, $model->id)->pluck('id');
        // $builder->whereIn('id', $organizationNodeIds);
    }
}
