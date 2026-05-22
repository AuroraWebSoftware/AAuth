<?php

use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\OrganizationScope;
use AuroraWebSoftware\AAuth\Services\OrganizationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    (new SampleDataSeeder())->run();
    $this->service = new OrganizationService();
});

/*
|--------------------------------------------------------------------------
| Recursive Organization Service Transaction Integrity
|--------------------------------------------------------------------------
|
| Previous implementation swallowed exceptions in try/catch and still
| committed the outer transaction, leaving the subtree partially mutated.
| The fix wraps recursion in DB::transaction() so any failure rolls back
| the entire subtree and re-throws to the caller.
|
*/

/**
 * Build a 3-level subtree under the seeded root for tests.
 *
 * Returns: ['root' => OrganizationNode, 'children' => [OrganizationNode, ...]]
 */
function buildTestSubtree(OrganizationService $service): array
{
    $scope = OrganizationScope::whereName('Sub-Scope')->first();

    $parent = $service->createOrganizationNode([
        'name' => 'tx_test_parent',
        'organization_scope_id' => $scope->id,
        'parent_id' => 1,
    ]);

    $childA = $service->createOrganizationNode([
        'name' => 'tx_test_child_a',
        'organization_scope_id' => $scope->id,
        'parent_id' => $parent->id,
    ]);

    $childB = $service->createOrganizationNode([
        'name' => 'tx_test_child_b',
        'organization_scope_id' => $scope->id,
        'parent_id' => $parent->id,
    ]);

    return ['root' => $parent, 'children' => [$childA, $childB]];
}

test('successful recursive delete removes the full subtree', function () {
    $tree = buildTestSubtree($this->service);

    $idsBefore = OrganizationNode::whereIn('id', [
        $tree['root']->id,
        $tree['children'][0]->id,
        $tree['children'][1]->id,
    ])->pluck('id')->toArray();
    expect($idsBefore)->toHaveCount(3);

    $this->service->deleteOrganizationNodesRecursively($tree['root']);

    $idsAfter = OrganizationNode::whereIn('id', [
        $tree['root']->id,
        $tree['children'][0]->id,
        $tree['children'][1]->id,
    ])->pluck('id')->toArray();
    expect($idsAfter)->toBeEmpty();
});

test('successful recursive path update writes all descendant paths', function () {
    $tree = buildTestSubtree($this->service);

    $this->service->updateNodePathsRecursively($tree['root']);

    $reloadedParent = OrganizationNode::find($tree['root']->id);
    $reloadedChildA = OrganizationNode::find($tree['children'][0]->id);

    // Parent path = root_path . parent_id
    expect($reloadedParent->path)->toContain((string) $tree['root']->id);

    // Child path = parent_path . '/' . child_id
    expect($reloadedChildA->path)->toContain($reloadedParent->path);
    expect($reloadedChildA->path)->toEndWith((string) $tree['children'][0]->id);
});

test('mid-recursion failure rolls back the entire subtree delete', function () {
    $tree = buildTestSubtree($this->service);

    // Register a transient deleting observer on OrganizationNode that throws
    // when we try to delete the second child. The expected behaviour is:
    //  - The first child is deleted within the transaction.
    //  - The second child's delete raises an exception.
    //  - The transaction rolls back EVERYTHING — first child re-appears,
    //    parent is still there.
    //  - The exception bubbles up to the caller (no silent swallow).

    $throwOnId = $tree['children'][1]->id;

    OrganizationNode::deleting(function (OrganizationNode $node) use ($throwOnId) {
        if ($node->id === $throwOnId) {
            throw new \RuntimeException('Simulated mid-recursion failure');
        }
    });

    $thrown = null;

    try {
        $this->service->deleteOrganizationNodesRecursively($tree['root']);
    } catch (\Throwable $e) {
        $thrown = $e;
    }

    // Detach the observer so subsequent tests aren't affected
    OrganizationNode::flushEventListeners();

    expect($thrown)->not->toBeNull();
    expect($thrown->getMessage())->toBe('Simulated mid-recursion failure');

    // All three nodes must still exist — the transaction rolled back
    $survivors = OrganizationNode::whereIn('id', [
        $tree['root']->id,
        $tree['children'][0]->id,
        $tree['children'][1]->id,
    ])->pluck('id')->toArray();
    expect($survivors)->toHaveCount(3);
});

test('mid-recursion failure rolls back path updates', function () {
    $tree = buildTestSubtree($this->service);

    $throwOnId = $tree['children'][1]->id;
    $originalRootPath = $tree['root']->path;

    // Saving observer throws unconditionally when child B is touched. We force
    // the recursion to touch every node by mutating the root's path on disk
    // first so the recursion has a different computed path to write everywhere.
    OrganizationNode::saving(function (OrganizationNode $node) use ($throwOnId) {
        if ($node->id === $throwOnId) {
            throw new \RuntimeException('Simulated path-update failure');
        }
    });

    // Pre-mutate the root path directly via DB (bypassing observers) so the
    // recursion has work to do — recomputed paths will differ from current.
    \Illuminate\Support\Facades\DB::table('organization_nodes')
        ->where('id', $tree['root']->id)
        ->update(['path' => $originalRootPath . '-stale']);

    $thrown = null;

    try {
        $freshRoot = OrganizationNode::find($tree['root']->id);
        $this->service->updateNodePathsRecursively($freshRoot);
    } catch (\Throwable $e) {
        $thrown = $e;
    }

    OrganizationNode::flushEventListeners();

    expect($thrown)->not->toBeNull();
    expect($thrown->getMessage())->toBe('Simulated path-update failure');

    // The pre-mutated stale path should still be in place — the recursive
    // update rolled back without writing the recomputed path.
    $reloadedRoot = OrganizationNode::find($tree['root']->id);
    expect($reloadedRoot->path)->toBe($originalRootPath . '-stale');
});

test('caller-managed transaction integrates: rolling back outer reverts subtree', function () {
    $tree = buildTestSubtree($this->service);

    try {
        DB::transaction(function () use ($tree) {
            $this->service->deleteOrganizationNodesRecursively($tree['root'], false);

            // Force the outer transaction to roll back
            throw new \RuntimeException('caller-rollback');
        });
    } catch (\RuntimeException $e) {
        // expected
    }

    // The subtree must still exist
    $survivors = OrganizationNode::whereIn('id', [
        $tree['root']->id,
        $tree['children'][0]->id,
        $tree['children'][1]->id,
    ])->pluck('id')->toArray();
    expect($survivors)->toHaveCount(3);
});

test('signature is backward compatible: $withDBTransaction parameter still accepted', function () {
    $tree = buildTestSubtree($this->service);

    // Old callers passing explicit true must keep working
    $this->service->deleteOrganizationNodesRecursively($tree['root'], true);

    $survivors = OrganizationNode::whereIn('id', [$tree['root']->id])->pluck('id')->toArray();
    expect($survivors)->toBeEmpty();
});

test('deep recursion (3+ levels) is atomic on failure', function () {
    // Build a 4-level subtree: root -> mid1 -> mid2 -> leaf
    $scope = OrganizationScope::whereName('Sub-Scope')->first();

    $root = $this->service->createOrganizationNode(['name' => 'deep_root', 'organization_scope_id' => $scope->id, 'parent_id' => 1]);
    $mid1 = $this->service->createOrganizationNode(['name' => 'deep_mid1', 'organization_scope_id' => $scope->id, 'parent_id' => $root->id]);
    $mid2 = $this->service->createOrganizationNode(['name' => 'deep_mid2', 'organization_scope_id' => $scope->id, 'parent_id' => $mid1->id]);
    $leaf = $this->service->createOrganizationNode(['name' => 'deep_leaf', 'organization_scope_id' => $scope->id, 'parent_id' => $mid2->id]);

    $allIds = [$root->id, $mid1->id, $mid2->id, $leaf->id];

    // Throw when trying to delete the leaf (deepest node, where DFS reaches first)
    $throwOnId = $leaf->id;

    OrganizationNode::deleting(function (OrganizationNode $node) use ($throwOnId) {
        if ($node->id === $throwOnId) {
            throw new \RuntimeException('Deep recursion failure');
        }
    });

    $thrown = null;

    try {
        $this->service->deleteOrganizationNodesRecursively($root);
    } catch (\Throwable $e) {
        $thrown = $e;
    }

    OrganizationNode::flushEventListeners();

    expect($thrown)->not->toBeNull();
    expect($thrown->getMessage())->toBe('Deep recursion failure');

    // Every node must still exist — savepoint rollback worked across all levels
    $survivors = OrganizationNode::whereIn('id', $allIds)->pluck('id')->toArray();
    expect($survivors)->toHaveCount(4);
});
