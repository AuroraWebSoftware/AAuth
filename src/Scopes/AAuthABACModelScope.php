<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AAuthABACModelScope implements Scope
{
    public function apply(Builder $builder, Model $model, $rules = false)
    {
        if ($rules === null) {
            $rules = [
                "&&" => [
                    ["==" => ["a", "asd"]],
                    ["==" => ["a", "asd"]],
                    [
                        "||" =>
                            [
                                ["==" => ["a", "asd"]],
                                ["==" => ["a", "asd"]],
                            ],
                    ],
                ],
            ];
        }



        $builder->where(function ($query) {
            $query->where('c', '=', 1)
                ->orWhere('d', '=', 1);
        });

        // todo
        // $organizationNodeIds = AAuth::organizationNodes(true, $model->id)->pluck('id');
        // $builder->whereIn('id', $organizationNodeIds);
    }
}
