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
     * Updates organization node paths recursively (breadth-first).
     *
     * Atomicity guarantee: the entire subtree update is wrapped in a single
     * database transaction (savepoints under nested calls). If any descendant
     * save fails, every prior change in the subtree is rolled back and the
     * original exception is re-thrown to the caller. Previous versions silently
     * swallowed exceptions and left the subtree in an inconsistent state.
     *
     * The `$withDBTransaction` parameter is preserved for backward compatibility:
     *  - true  (default): open a top-level transaction here.
     *  - false           : the caller is already managing a transaction; participate in it.
     *
     * @param  OrganizationNode  $node
     * @param  bool|null  $withDBTransaction
     * @return void
     *
     * @throws \Throwable
     */
    public function updateNodePathsRecursively(OrganizationNode $node, ?bool $withDBTransaction = true): void
    {
        if ($withDBTransaction) {
            DB::transaction(fn () => $this->updateSubtreePaths($node));

            return;
        }

        $this->updateSubtreePaths($node);
    }

    /**
     * Inner recursion for path updates. Does not manage transactions.
     *
     * @param  OrganizationNode  $node
     * @return void
     */
    protected function updateSubtreePaths(OrganizationNode $node): void
    {
        $node->path = $this->getPath($node->parent_id) . $node->id;
        $node->save();

        foreach (OrganizationNode::whereParentId($node->id)->get() as $subNode) {
            $this->updateSubtreePaths($subNode);
        }
    }

    /**
     * Deletes organization nodes recursively (depth-first).
     *
     * Atomicity guarantee: identical to updateNodePathsRecursively(). The whole
     * subtree deletion is atomic; partial failures roll back the entire subtree
     * and re-throw the original exception. Previous versions silently swallowed
     * exceptions.
     *
     * @param  OrganizationNode  $node
     * @param  bool|null  $withDBTransaction
     * @return void
     *
     * @throws \Throwable
     */
    public function deleteOrganizationNodesRecursively(OrganizationNode $node, ?bool $withDBTransaction = true): void
    {
        if ($withDBTransaction) {
            DB::transaction(fn () => $this->deleteSubtree($node));

            return;
        }

        $this->deleteSubtree($node);
    }

    /**
     * Inner recursion for deletes. Does not manage transactions.
     *
     * @param  OrganizationNode  $node
     * @return void
     */
    protected function deleteSubtree(OrganizationNode $node): void
    {
        foreach (OrganizationNode::whereParentId($node->id)->get() as $subNode) {
            $this->deleteSubtree($subNode);
        }

        $node->delete();
    }
}
