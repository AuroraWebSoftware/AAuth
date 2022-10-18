<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use AuroraWebSoftware\AAuth\Services\ABACService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AAuthABACModelScope implements Scope
{
    /**
     * @throws Exception
     */
    public function apply(Builder $builder, Model $model, $rules = false)
    {
        if ($rules === false) {
            $rules = AAuth::ABACRules($model::getModelType()) ?? [];
            ABACService::validateAbacRuleArray($rules);
        }

        // todo refactor gerekebilir
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
                    if ($suboperator == '&&') {
                        $builder->orWhere(
                            function ($query) use ($subrule, $model) {
                                $this->apply($query, $model, $subrule);
                            }
                        );
                    } elseif ($suboperator == '||') {
                        $builder->orWhere(
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
    }
}
