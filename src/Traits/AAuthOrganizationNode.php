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
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * @method static addGlobalScope(AAuthOrganizationNodeScope $param)
 */
trait AAuthOrganizationNode
{
    public static function bootAAuthOrganizationNode(): void
    {
        static::addGlobalScope(new AAuthOrganizationNodeScope);
        /*
        static::saving(function ($model) {
            $model->slug = $model->generateSlug($model->title);
        });
         */
    }

    public function allWithoutAAuthOrganizationNodeScope(): mixed
    {
        return self::withoutGlobalScopes()->all();
    }

    public function relatedAAuthOrganizationNode(): Model|OrganizationNode|Builder|null
    {
        return OrganizationNode::whereModelId($this->getModelId())
            ->whereModelType(self::getModelType())
            ->first();
    }

    /**
     * Enforce the active role's org-subtree boundary on writes — but only when an AAuth
     * context is resolvable. Seeders, console commands and queue jobs run without an
     * authenticated role and are intentionally skipped (no context = no enforcement).
     *
     * @throws Throwable
     */
    protected static function assertOrganizationNodeAuthorized(int $nodeId): void
    {
        try {
            $aauth = app('aauth');
        } catch (Throwable $e) {
            return;
        }

        // Throws InvalidOrganizationNodeException when the node is outside the subtree.
        $aauth->organizationNode($nodeId);
    }

    /**
     * @throws Throwable
     */
    public static function createWithAAuthOrganizationNode(array $modelCreateData, int $parentOrganizationNodeId, int $organizationScopeId)
    {
        // todo di
        $organizationService = new OrganizationService;

        $parentOrganizationNode = OrganizationNode::find($parentOrganizationNodeId);

        throw_if($parentOrganizationNode == null, new InvalidOrganizationNodeException);

        $organizationScope = OrganizationScope::find($organizationScopeId);

        throw_if($organizationScope == null, new InvalidOrganizationScopeException);

        // Reject grafting under a parent outside the active role's accessible subtree.
        self::assertOrganizationNodeAuthorized($parentOrganizationNodeId);

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

        $organizationService = new OrganizationService;

        $parentOrganizationNode = OrganizationNode::find($parentOrganizationNodeId);

        throw_if($parentOrganizationNode == null, new InvalidOrganizationNodeException);

        $organizationScope = OrganizationScope::find($organizationScopeId);

        throw_if($organizationScope == null, new InvalidOrganizationScopeException);

        self::assertOrganizationNodeAuthorized($parentOrganizationNodeId);

        return DB::transaction(function () use ($modelId, $nodeId, $modelUpdateData, $parentOrganizationNode, $organizationScope, $organizationService) {
            $modelInfo = self::findOrFail($modelId);
            $updatedModel = $modelInfo->update($modelUpdateData);

            // Persist the node's new parent/scope/name, THEN recompute the whole
            // subtree path (updateNodePathsRecursively only recomputes; it does not
            // persist field changes on its own).
            $node = OrganizationNode::findOrFail($nodeId);
            $node->update([
                'name' => $modelInfo->getModelName(),
                'organization_scope_id' => $organizationScope->id,
                'parent_id' => $parentOrganizationNode->id,
            ]);

            $organizationService->updateNodePathsRecursively($node, false);

            return $updatedModel;
        });
    }

    /**
     * @return bool
     *
     * @throws Throwable
     */
    public static function deleteWithAAuthOrganizationNode(int $modelId)
    {

        $organizationService = new OrganizationService;

        return DB::transaction(function () use ($modelId, $organizationService) {
            $organizationNode = OrganizationNode::where('model_id', $modelId)
                ->where('model_type', self::getModelType())
                ->first();

            throw_if($organizationNode == null, new InvalidOrganizationNodeException);

            self::assertOrganizationNodeAuthorized($organizationNode->id);

            $modelInfo = self::findOrFail($modelId);
            $modelInfo->delete();

            // Pass the model (not its id) — deleteOrganizationNodesRecursively expects
            // an OrganizationNode; participate in the outer transaction.
            $organizationService->deleteOrganizationNodesRecursively($organizationNode, false);

            return true;
        });
    }
}
