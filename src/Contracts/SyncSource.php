<?php

namespace Splicewire\Sync\Contracts;

use Splicewire\Sync\Graph\DependencyResolver;

/**
 * The ingest seam the sync engine reads FROM. A source yields `[class, sourceId, payload]` tuples
 * — already topo-ordered or resolvable via {@see DependencyResolver}. The
 * local tenant source reads {@see Syncable} models in a tenant context (lives in tower-tenancy); a
 * remote source (HTTP / export-file / another site) yields the SAME tuples off a serialized feed with
 * no `Syncable` model instance required on the ingest side.
 *
 * This port is why the base package must NOT depend on tenancy: a remote source has no tenant context.
 */
interface SyncSource
{
    /**
     * Yield `[class, sourceId, payload]` tuples, already topo-ordered or resolvable.
     *
     * @param  array<class-string, list<string>>  $scope
     * @return iterable<array{0: class-string, 1: string, 2: array<string, mixed>}>
     */
    public function stream(array $scope = []): iterable;
}
