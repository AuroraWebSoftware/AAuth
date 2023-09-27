<?php

namespace AuroraWebSoftware\AAuth\Services;

use AuroraWebSoftware\AAuth\Http\Requests\StoreOrganizationNodeRequest;
use AuroraWebSoftware\AAuth\Http\Requests\StoreOrganizationScopeRequest;
use AuroraWebSoftware\AAuth\Http\Requests\UpdateOrganizationScopeRequest;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization Data Service
 */
class OrganizationService
{
    /**
     * Creates an org. scope with given array
     *
     * @param array $organizationScope
     * @param bool $withValidation
     * @return OrganizationScope
     *
     * @throws ValidationException
     */
    public function createOrganizationScope(array $organizationScope, bool $withValidation = true): OrganizationScope
    {
        if ($withValidation) {
            $validator = Validator::make($organizationScope, StoreOrganizationScopeRequest::getRules());
            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                throw new ValidationException($validator, new Response($message, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }

        return OrganizationScope::create($organizationScope);
    }

    /**
     * Updates a Perm.
     *
     * @param array $organizationScope
     * @param int $id
     * @param bool $withValidation
     * @return ?OrganizationScope
     *
     * @throws ValidationException
     */
    public function updateOrganizationScope(array $organizationScope, int $id, bool $withValidation = true): ?OrganizationScope
    {
        if ($withValidation) {
            $validator = Validator::make($organizationScope, UpdateOrganizationScopeRequest::$rules);

            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                throw new ValidationException($validator, new Response($message, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }
        $organizationScopeModel = OrganizationScope::find($id);

        return $organizationScopeModel->update($organizationScope) ? $organizationScopeModel : null;
    }

    /**
     * deletes perm.
     *
     * @param int $id
     * @return bool|null
     */
    public function deleteOrganizationScope(int $id): ?bool
    {
        return OrganizationScope::find($id)->delete();
    }

    /**
     * Creates an org. node with given array
     *
     * @param array $organizationNode
     * @param bool $withValidation
     * @return OrganizationNode
     *
     * @throws ValidationException
     */
    public function createOrganizationNode(array $organizationNode, bool $withValidation = true): OrganizationNode
    {
        // todo scope eşleşmeleri
        if ($withValidation) {
            $validator = Validator::make($organizationNode, StoreOrganizationNodeRequest::$rules);
            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                throw new ValidationException($validator, new Response($message, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }

        $parentPath = $this->getPath($organizationNode['parent_id'] ?? null);

        // add temp path before determine actual path
        $organizationNode['path'] = $parentPath . '/?';
        $organizationNode = OrganizationNode::create($organizationNode);

        // todo , can be add inside model's created event
        $organizationNode->path = $parentPath . $organizationNode->id;
        $organizationNode->save();

        return $organizationNode;
    }

    /**
     * @param Model $model
     * @param int $parentOrganizationId
     * @return OrganizationNode|null
     */
    public function createOrganizationNodeForModel(Model $model, int $parentOrganizationId): ?OrganizationNode
    {
        return null;
    }

    /**
     * Return path with trailing slash (/)
     * @param int|null $organizationNodeId
     * @return string|null
     */
    public function getPath(?int $organizationNodeId): ?string
    {
        if ($organizationNodeId == null) {
            return '';
        }

        return OrganizationNode::find($organizationNodeId)?->path . '/';
    }

    /**
     * @param int $organizationNodeId
     */
    public function calculatePath(int $organizationNodeId): void
    {
        // todo
    }

    /**
     * Updates organization node recursively using breadth first method
     * @param OrganizationNode $node
     * @param bool|null $withDBTransaction
     * @return void
     */
    public function updateNodePathsRecursively(OrganizationNode $node, ?bool $withDBTransaction = true): void
    {
        if ($withDBTransaction) {
            DB::beginTransaction();
        }

        try {
            $node->path = $this->getPath($node->parent_id) . $node->id;
            $node->save();

            $subNodes = OrganizationNode::whereParentId($node->id)->get();

            foreach ($subNodes as $subNode) {
                $this->updateNodePathsRecursively($subNode, false);
            }

        } catch (\Exception $exception) {
            DB::rollback();
        }

        if ($withDBTransaction) {
            DB::commit();
        }
    }

    /**
     * deletes organization nodes using depth first search
     * @param OrganizationNode $node
     * @param bool|null $withDBTransaction
     * @return void
     */
    public function deleteOrganizationNodesRecursively(OrganizationNode $node, ?bool $withDBTransaction = true): void
    {
        if ($withDBTransaction) {
            DB::beginTransaction();
        }

        try {
            //
            $subNodes = OrganizationNode::whereParentId($node->id)->get();

            foreach ($subNodes as $subNode) {
                $this->deleteOrganizationNodesRecursively($subNode, false);
            }

            $node->delete();

        } catch (\Exception $exception) {
            DB::rollback();
        }

        if ($withDBTransaction) {
            DB::commit();
        }

    }
}
