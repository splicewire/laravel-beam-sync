<?php

namespace Splicewire\Sync\Graph;

use Illuminate\Support\Facades\Log;
use Rushing\Graphine\Algorithms\TopologicalSort;
use Splicewire\Sync\Contracts\Syncable;

/**
 * The generic, tenancy-free scope-expand + Kahn topological sort over {@see Syncable::syncDependencies()}.
 *
 * Pure graph work: from a declared scope, walk `syncDependencies()` until stable, then order the
 * expanded set so every dependency precedes its dependents. It only calls `$class::find($id)` on
 * Syncable models — there is NO tenancy, capability, or lineage concern here. Extracted DOWN into
 * splicewire/laravel-beam-sync (issue 17 P1) so BOTH a tenant target and a remote/CLI target reuse it;
 * the caller wraps it in whatever context switching its topology needs.
 *
 * Behaviour-preserving: this is the exact logic previously inlined on TenantSyncEngine (expandScope /
 * topologicalSort / kahnSort). The Kahn kernel is no longer hand-rolled here — the ordering step
 * delegates to the shared, driver-agnostic {@see TopologicalSort} kernel;
 * this class keeps only the Syncable-specific concerns: the syncDependencies() walk and the
 * degrade-on-cycle policy (warn, then append the cyclic remainder rather than throw).
 */
class DependencyResolver
{
    /**
     * Starting from the declared scope, walk syncDependencies() until stable.
     * Must be called inside whatever read context resolves the Syncable models.
     *
     * @param  array<class-string, list<string>>  $scope
     * @return array<class-string, list<string>>
     */
    public function expandScope(array $scope): array
    {
        $resolved = [];

        foreach ($scope as $class => $ids) {
            $resolved[$class] = array_values(array_unique($ids));
        }

        $queue = $resolved;

        while (! empty($queue)) {
            $nextQueue = [];

            foreach ($queue as $class => $ids) {
                foreach ($ids as $id) {
                    /** @var Syncable $entity */
                    $entity = $class::find($id);
                    if (! $entity) {
                        continue;
                    }

                    foreach ($entity->syncDependencies() as $depClass => $depIds) {
                        foreach ($depIds as $depId) {
                            if (in_array($depId, $resolved[$depClass] ?? [], true)) {
                                continue;
                            }
                            $resolved[$depClass][] = $depId;
                            $nextQueue[$depClass][] = $depId;
                        }
                    }
                }
            }

            $queue = $nextQueue;
        }

        foreach ($resolved as $class => $ids) {
            $resolved[$class] = array_values(array_unique($ids));
        }

        return $resolved;
    }

    /**
     * Topological sort of the expanded entity set.
     * Must be called inside whatever read context resolves the Syncable models.
     *
     * @param  array<class-string, list<string>>  $entitySet
     * @return list<array{0: class-string, 1: string}>
     */
    public function topologicalSort(array $entitySet): array
    {
        $allNodes = [];
        $adjacency = [];

        foreach ($entitySet as $class => $ids) {
            foreach ($ids as $id) {
                $allNodes["{$class}::{$id}"] = [$class, $id];
            }
        }

        foreach ($entitySet as $class => $ids) {
            foreach ($ids as $id) {
                $key = "{$class}::{$id}";
                $entity = $class::find($id);
                if (! $entity) {
                    continue;
                }

                foreach ($entity->syncDependencies() as $depClass => $depIds) {
                    foreach ($depIds as $depId) {
                        $depKey = "{$depClass}::{$depId}";
                        if (isset($allNodes[$depKey])) {
                            // A prerequisite precedes its dependent: in the kernel's
                            // successor convention that is the directed edge dep → key.
                            $adjacency[$depKey][] = $key;
                        }
                    }
                }
            }
        }

        $order = TopologicalSort::kahn(array_keys($allNodes), $adjacency);

        $sorted = array_map(static fn (string $key) => $allNodes[$key], $order->sorted);

        // Degrade, don't throw: a dependency cycle leaves nodes unordered. Warn and
        // append the cyclic remainder so the caller still gets every entity.
        if ($order->hasCycle()) {
            Log::warning('DependencyResolver: dependency cycle detected; some entities may be written out of order.');
            foreach ($order->cyclic as $key) {
                $sorted[] = $allNodes[$key];
            }
        }

        return $sorted;
    }
}
