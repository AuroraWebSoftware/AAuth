<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AAuthABACModelScope implements Scope
{
    public function apply(Builder $builder, Model $model, $rules = false, $operator = '&&')
    {
        if ($rules === false) {
            /*
            $rules = [
                "&&" => [
                    ["like" => ["name", "asd"]],
                    ["=" => ["asd", "asd"]],
                    [
                        "||" =>
                            [
                                ["=" => ["a", "asd"]],
                                ["=" => ["a", "asd"]],
                            ],
                    ],
                ],
            ];

            $rules = [
                "&&" => [
                    ["like" => ["name", "%Nodeable 1%"]],
                ],
            ];
            */
            $rules = AAuth::ABACRules($model::getModelType());
        }

        // todo second level nested closres
        // clousere döndüren bir fonkisyon yazılmalı

        if (array_key_exists('&&', $rules)) {
            $this->apply($builder, $model, $rules['&&'], '&&');
        } elseif (array_key_exists('||', $rules)) {
            $this->apply($builder, $model, $rules['||'], '||');
        } else {
            if ($operator == '&&') {
                foreach ($rules as $key => $rule) {
                    $builder->where($rule[array_key_first($rule)][0], array_key_first($rule), $rule[array_key_first($rule)][1]);
                }
            } elseif ($operator == '||') {
                foreach ($rules as $key => $rule) {
                    $builder->orWhere($rule[0][0], $key, $rule[0][1]);
                }
            }
        }


        /*
                $builder->where(function ($query) {
                    $query->where('c', '=', 1)
                        ->orWhere('d', '=', 1);
                });
        */
        // todo
        // $organizationNodeIds = AAuth::organizationNodes(true, $model->id)->pluck('id');
        // $builder->whereIn('id', $organizationNodeIds);
    }
}
