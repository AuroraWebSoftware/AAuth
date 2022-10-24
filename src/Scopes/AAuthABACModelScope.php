<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use AuroraWebSoftware\AAuth\Utils\ABACUtil;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AAuthABACModelScope implements Scope
{
    /**
     * @throws Exception
     */
    public function apply(Builder $builder, Model $model, $rules = false, $parentOperator = '&&')
    {
        if ($rules === false) {
            $rules = AAuth::ABACRules($model::getModelType()) ?? [];
            ABACUtil::validateAbacRuleArray($rules);

            return $builder->where(
                function ($query) use ($rules, $model) {
                    $this->apply($query, $model, $rules);
                }
            );
        }

        // todo refactor gerekebilir
        foreach ($rules as $key => $rule) {
            if ($key == '&&') {
                if ($parentOperator == '||') {
                    $builder->orWhere(
                        function ($query) use ($rule, $model) {
                            $this->apply($query, $model, $rule);
                        }
                    );
                } else {
                    $builder->where(
                        function ($query) use ($rule, $model) {
                            $this->apply($query, $model, $rule);
                        }
                    );
                }
            } elseif ($key == '||') {
                if ($parentOperator == '||') {
                    $builder->orWhere(
                        function ($query) use ($rule, $model) {
                            $this->apply($query, $model, $rule, '||');
                        }
                    );
                } else {
                    $builder->where(
                        function ($query) use ($rule, $model) {
                            $this->apply($query, $model, $rule, '||');
                        }
                    );
                }
            } else {
                $operator = array_key_first($rule);
                if ($parentOperator == '||') {
                    $builder->orWhere(
                        $rule[array_key_first($rule)]['attribute'],
                        $operator,
                        $rule[array_key_first($rule)]['value']
                    );
                } else {
                    $builder->where(
                        $rule[array_key_first($rule)]['attribute'],
                        $operator,
                        $rule[array_key_first($rule)]['value']
                    );
                }
            }
        }


        // todo refactor gerekebilir
        /*
        foreach ($rules as $key => $rule) {
            if ($key == '&&') {
                foreach ($rule as $subkey => $subrule) {
                    $suboperator = array_key_first($subrule);

                    if ($suboperator == '&&' or $suboperator == '||') {
                        $builder->where(
                            function ($query) use ($subrule, $model) {
                                $this->apply($query, $model, $subrule);
                            }
                        );
                    } else {
                        $builder->where(
                            $subrule[array_key_first($subrule)]['attribute'],
                            $suboperator,
                            $subrule[array_key_first($subrule)]['value']
                        );
                    }
                }
            } elseif ($key == '||') {
                foreach ($rule as $subkey => $subrule) {
                    $suboperator = array_key_first($subrule);
                    if ($suboperator == '&&' || $suboperator == '||') {
                        $builder->where(
                            function ($query) use ($subrule, $model) {
                                $this->apply($query, $model, $subrule);
                            }
                        );
                    } else {
                        $builder->orWhere(
                            $subrule[array_key_first($subrule)]['attribute'],
                            $suboperator,
                            $subrule[array_key_first($subrule)]['value']
                        );
                    }
                }
            }
        }
        */
    }
}
