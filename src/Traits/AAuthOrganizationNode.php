<?php

namespace Aurora\AAuth\Traits;

use Aurora\AAuth\Exceptions\InvalidOrganizationNodeException;
use Aurora\AAuth\Exceptions\InvalidOrganizationScopeException;
use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Models\OrganizationScope;
use Aurora\AAuth\Scopes\AAuthOrganizationNodeScope;
use Aurora\AAuth\Services\OrganizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static addGlobalScope(AAuthOrganizationNodeScope $param)
 */
trait AAuthOrganizationNode
{
    // model'in namespace ini alabilir miyiz?
    public function aauth()
    {
    }

    public static function bootAAuthOrganizationNode()
    {
        static::addGlobalScope(new AAuthOrganizationNodeScope());

        /*
         * static::saving(function ($model) {
            $model->slug = $model->generateSlug($model->title);
        });

        static::saving(function ($model) {
        $settings = $model->sluggable();
        $model->slug = $model->generateSlug($settings['source']);
    });

         */
    }

    public function allWithoutAAuthOrganizationNodeScope()
    {
        return self::withoutGlobalScopes()->all();
    }

    /**
     * @return OrganizationNode|Builder|Model|null
     */
    public function relatedAAuthOrganizationNode(): Model|OrganizationNode|Builder|null
    {
        return OrganizationNode::whereModelId($this->getModelId())
            ->whereModelType(self::getModelType())
            ->first();
    }

    /**
     * @throws \Throwable
     */
    public static function createWithAAuthOrganizationNode(array $modelCreateData, int $parentOrganizationNodeId, int $organizationScopeId)
    {
        // todo di
        $organizationService = new OrganizationService();

        // todo yetki kontrolü ? serviste mi olmalı?
        // gerekli validationlar, organization scope validationları vs.
        // commit rollback
        $parentOrganizationNode = OrganizationNode::find($parentOrganizationNodeId)?->first();

        throw_if($parentOrganizationNode == null, new InvalidOrganizationNodeException());

        $organizationScope = OrganizationScope::find($organizationScopeId)?->first();

        throw_if($organizationScope == null, new InvalidOrganizationScopeException());

        $createdModel = self::create($modelCreateData);

        $OrgNodeCreateData = [
            'name' => $createdModel->getModelName(),
            'organization_scope_id' => $organizationScope->id,
            'parent_id' => $parentOrganizationNode->id,
            'model_type' => self::getModelType(),
            'model_id' => $createdModel->getModelId(),
        ];
        $createdON = $organizationService->createOrganizationNode($OrgNodeCreateData);

        return $createdModel;
    }
}
