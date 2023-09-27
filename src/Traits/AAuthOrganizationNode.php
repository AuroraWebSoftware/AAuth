<?php

namespace AuroraWebSoftware\AAuth\Traits;

use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationScopeException;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\OrganizationScope;
use AuroraWebSoftware\AAuth\Scopes\AAuthOrganizationNodeScope;
use AuroraWebSoftware\AAuth\Services\OrganizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * @method static addGlobalScope(AAuthOrganizationNodeScope $param)
 */
trait AAuthOrganizationNode
{
    /**
     * @return void
     */
    public static function bootAAuthOrganizationNode(): void
    {
        static::addGlobalScope(new AAuthOrganizationNodeScope());
        /*
        static::saving(function ($model) {
            $model->slug = $model->generateSlug($model->title);
        });
         */
    }

    /**
     * @return mixed
     */
    public function allWithoutAAuthOrganizationNodeScope(): mixed
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
     * @throws Throwable
     */
    public static function createWithAAuthOrganizationNode(array $modelCreateData, int $parentOrganizationNodeId, int $organizationScopeId)
    {
        // todo di
        $organizationService = new OrganizationService();

        // todo yetki kontrolü ? serviste mi olmalı?
        // gerekli validationlar, organization scope validationları vs.
        // commit rollback
        $parentOrganizationNode = OrganizationNode::find($parentOrganizationNodeId);

        throw_if($parentOrganizationNode == null, new InvalidOrganizationNodeException());

        $organizationScope = OrganizationScope::find($organizationScopeId);

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

    /**
     * @throws Throwable
     */
    public static function updateWithAAuthOrganizationNode(int $modelId, int $nodeId, array $modelUpdateData, int $parentOrganizationNodeId, int $organizationScopeId)
    {

        $organizationService = new OrganizationService();

        $parentOrganizationNode = OrganizationNode::find($parentOrganizationNodeId);

        throw_if($parentOrganizationNode == null, new InvalidOrganizationNodeException());

        $organizationScope = OrganizationScope::find($organizationScopeId);

        throw_if($organizationScope == null, new InvalidOrganizationScopeException());


        $modelInfo = self::find($modelId);

        $updatedModel = $modelInfo->update($modelUpdateData);



        $OrgNodeUpdateData = [
            'name' => $modelInfo->getModelName(),
            'organization_scope_id' => $organizationScope->id,
            'parent_id' => $parentOrganizationNode->id,
            'model_type' => self::getModelType(),
            'model_id' => $modelId,
        ];
        $updateON = $organizationService->updateOrganizationNodesRecursively($OrgNodeUpdateData, $nodeId);

        return $updatedModel;
    }

    /**
     * @param int $modelId
     * @return bool
     * @throws Throwable
     */
    public static function deleteWithAAuthOrganizationNode(int $modelId)
    {

        $organizationService = new OrganizationService();

        $organizationNode = OrganizationNode::where('model_id', $modelId)->first();


        throw_if($organizationNode == null, new InvalidOrganizationNodeException());


        $modelInfo = self::findOrFail($modelId);

        $deleteModel = $modelInfo->delete($modelInfo);


        $deleteON = $organizationService->deleteOrganizationNodesRecursively($organizationNode->id);

        return true;
    }
}
