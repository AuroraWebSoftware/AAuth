<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use AuroraWebSoftware\AAuth\Interfaces\AAuthABACModelInterface;
use AuroraWebSoftware\AAuth\Utils\ABACUtil;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @template TModelClass of Model
 */
class AAuthABACModelScope implements Scope
{
    /**
     * @param Builder<TModelClass> $builder
     * @param Model $model
     * @param mixed $rules
     * @param string $parentOperator
     * @return void
     * @throws Exception
     */
    public function apply(Builder $builder, Model $model, mixed $rules = false, string $parentOperator = '&&'): void
    {
        if ($rules === false) {
            /**
             * @var AAuthABACModelInterface $model
             *
             * PHPStan analysis does not return any errors, but it underlines the ABACRules method because it somehow
             * does not see it, even though it is defined in the facade.
             * @phpstan-ignore-next-line
             */
            $rules = AAuth::ABACRules($model::getModelType()) ?? [];

            /**
             * @var array $rules
             */
            ABACUtil::validateAbacRuleArray($rules);

            $builder->where(function ($query) use ($rules, $model) {
                /**
                 * @var Model $model
                 */
                $this->apply($query, $model, $rules);
            });
        } else {
            $logicalOperators = ["&&","||"];

            foreach ($rules as $rule) {
                $firstKey = array_key_first($rule);
                $abacRule = $rule[$firstKey];

                if (in_array($firstKey, $logicalOperators)) {
                    $this->applyLogicalOperator($builder, $abacRule, $model, $firstKey, $parentOperator);
                } else {
                    $this->applyConditionalOperator($builder, $rule, $parentOperator);
                }
            }
        }
    }

    /**
     * Apply logical operator (&& or ||) to the query builder.
     *
     * @param Builder<TModelClass> $builder
     * @param array $abacRule
     * @param Model $model
     * @param string $logicalOperator
     * @param string $parentOperator
     *
     * @return void
     * @throws Exception
     */
    protected function applyLogicalOperator(Builder $builder, array $abacRule, Model $model, string $logicalOperator, string $parentOperator): void
    {
        $queryMethod = $parentOperator == '&&' ? 'where' : 'orWhere';

        $builder->{$queryMethod}(function ($query) use ($abacRule, $model, $logicalOperator) {
            $this->apply($query, $model, $abacRule, $logicalOperator);
        });
    }

    /**
     * Apply conditional operator to the query builder.
     *
     * @param Builder<TModelClass> $builder
     * @param array   $rule
     * @param string  $parentOperator
     *
     * @return void
     */
    protected function applyConditionalOperator(Builder $builder, array $rule, string $parentOperator): void
    {
        $operator = array_key_first($rule);

        $queryMethod = $parentOperator == '||' ? 'orWhere' : 'where';

        $from = sprintf('%s.', is_string($builder->getQuery()->from) ? $builder->getQuery()->from : '');

        $builder->{$queryMethod}(
            $from.$rule[$operator]['attribute'],
            $operator,
            $rule[$operator]['value']
        );
    }
}
