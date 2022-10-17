<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AAuthABACModelScope implements Scope
{
    public function apply(Builder $builder, Model $model, $rules = false, $operator = false)
    {
        if ($rules === false) {
            $rules = AAuth::ABACRules($model::getModelType());
        }


        foreach ($rules as $key => $rule) {
            if ($key == '&&') {
                foreach ($rule as $subkey => $subrule) {
                    $suboperator = array_key_first($subrule);

                    if ($suboperator == '&&') {
                        $builder->where(
                            function ($query) use ($subrule, $model) {
                                $this->apply($query, $model, $subrule);
                            }
                        );
                    } elseif ($suboperator == '||') {
                    } else {
                        $builder->where(
                            $subrule[array_key_first($subrule)]['attribute'],
                            $suboperator,
                            $subrule[array_key_first($subrule)]['value']
                        );
                    }
                }
            }
        }


        /*
        if (array_key_exists('&&', $rules)) {

            foreach ($rules['&&'] as $key => $rule) {

                if ($key == '&&' or $key == '||') {
                    $builder->where(function ($query) use ($model, $rule) {
                        $this->apply($query, $model, $rule);
                    });
                } else {
                    $builder->where($rule[array_key_first($rule)][0], array_key_first($rule), $rule[array_key_first($rule)][1]);
                }
            }
        }


        // todo second level nested closres
        // clousere döndüren bir fonkisyon yazılmalı

        /*
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
        */
    }

    public function a()
    {
    }
}
