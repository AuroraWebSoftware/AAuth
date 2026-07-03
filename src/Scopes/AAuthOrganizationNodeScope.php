<?php

namespace AuroraWebSoftware\AAuth\Scopes;

use AuroraWebSoftware\AAuth\Facades\AAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AAuthOrganizationNodeScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationNodesQuery = AAuth::organizationNodesQuery(true, $model::getModelType());
        $query = $builder->getQuery();
        $from = $this->getFromTableName($query->from);
        $query->wheres = array_map(function ($where) use ($from) {
            return $this->prefixWhereColumn($where, $from);
        }, $query->wheres);

        $builder->join('organization_nodes', 'organization_nodes.model_id', '=', sprintf('%s.id', $from))
            ->select($this->getSelectColumns($from));

        $builder->mergeWheres($organizationNodesQuery->getQuery()->wheres, $organizationNodesQuery->getBindings());
    }

    /**
     * Get the table name from the query's "from" clause
     */
    protected function getFromTableName(mixed $from): string
    {
        return is_string($from) ? $from : '';
    }

    /**
     * Prefix the where column with the table name if needed
     */
    protected function prefixWhereColumn(array $where, string $from): array
    {
        if (isset($where['column']) && ! str_contains($where['column'], $from.'.')) {
            $where['column'] = sprintf('%s.%s', $from, $where['column']);
        }

        return $where;
    }

    /**
     * Get the select columns for the query (only selects fields from the left table)
     */
    protected function getSelectColumns(string $from): array
    {
        return ["{$from}.*"];
    }
}
