<?php

namespace AuroraWebSoftware\AAuth\Services;

use AuroraWebSoftware\AAuth\Http\Requests\StoreOrganizationNodeRequest;
use AuroraWebSoftware\AAuth\Http\Requests\StoreOrganizationScopeRequest;
use AuroraWebSoftware\AAuth\Http\Requests\UpdateOrganizationNodeRequest;
use AuroraWebSoftware\AAuth\Http\Requests\UpdateOrganizationScopeRequest;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
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
     * @param OrganizationNode $node
     * @return void
     */
    public function updateNodePath(OrganizationNode $node): void
    {
        $node->path = $this->getPath($node->parent_id) . $node->id;
        $node->save();
    }

    /**
     * @param array $organizationNode
     * @param int $id
     * @param bool $withValidation
     * @return OrganizationNode|bool
     */
    public function updateOrganizationNode(array $organizationNode, int $id, bool $withValidation = true): OrganizationNode|bool
    {
        if ($withValidation) {
            $validator = Validator::make($organizationNode, UpdateOrganizationNodeRequest::$rules);

            if ($validator->fails()) {
                $message = implode(' , ', $validator->getMessageBag()->all());

                throw new ValidationException($validator, new Response($message, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }


        $organizationNodeModel = OrganizationNode::find($id);

        $this->updateNodePath($organizationNodeModel);

        $subNodeIds = OrganizationNode::whereParentId($id)->pluck('id');


        foreach ($subNodeIds as $subNodeId) {
            $subNode = OrganizationNode::find($subNodeId);

            if ($subNode) {
                $this->updateNodePath($subNode);
            }
        }


        return $organizationNodeModel->update($organizationNode) ? $organizationNodeModel : false;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteOrganizationNode(int $id): bool
    {
        try {

            $subNodeIds = OrganizationNode::where('parent_id', $id)->pluck('id');

            foreach ($subNodeIds as $subNodeId) {

                $subNodeInfo = OrganizationNode::findOrFail($subNodeId);
                $subNodeModel = $subNodeInfo->model_type;
                $subNodeModel::findOrFail($subNodeInfo->model_id)->delete();
                $subNodeInfo->delete();
            }

            $parentNode = OrganizationNode::findOrFail($id);
            $parentNode->delete();

            return true;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {

            return false;
        }

    }
}
