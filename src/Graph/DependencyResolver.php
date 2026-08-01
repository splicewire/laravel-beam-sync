<?php

namespace Splicewire\Sync\Graph;

use Illuminate\Support\Facades\Log;
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
 * topologicalSort / kahnSort), moved without change.
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
        $deps = [];
        $allNodes = [];

        foreach ($entitySet as $class => $ids) {
            foreach ($ids as $id) {
                $key = "{$class}::{$id}";
                $allNodes[$key] = [$class, $id];
                $deps[$key] = [];
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
                            $deps[$key][] = $depKey;
                        }
                    }
                }
            }
        }

        return $this->kahnSort($allNodes, $deps);
    }

    /**
     * Kahn's algorithm for topological sort.
     *
     * @param  array<string, array{0: class-string, 1: string}>  $nodes
     * @param  array<string, list<string>>  $deps  node => deps it depends on
     * @return list<array{0: class-string, 1: string}>
     */
    private function kahnSort(array $nodes, array $deps): array
    {
        $inDegree = array_fill_keys(array_keys($nodes), 0);
        $dependents = array_fill_keys(array_keys($nodes), []);

        foreach ($deps as $node => $nodeDeps) {
            foreach ($nodeDeps as $dep) {
                $inDegree[$node]++;
                $dependents[$dep][] = $node;
            }
        }

        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        $sorted = [];
        while (! empty($queue)) {
            $node = array_shift($queue);
            $sorted[] = $nodes[$node];

            foreach ($dependents[$node] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        if (count($sorted) < count($nodes)) {
            Log::warning('DependencyResolver: dependency cycle detected; some entities may be written out of order.');
            foreach ($nodes as $key => $node) {
                if ($inDegree[$key] > 0) {
                    $sorted[] = $node;
                }
            }
        }

        return $sorted;
    }
}
