<?php

namespace Splicewire\Sync\Contracts;

/**
 * The write seam the sync engine drives THROUGH. A target owns "has this source entity already
 * landed at this version?" (lineage/hash diff) and "apply one payload, record lineage, return the
 * landed id". The tenancy push-to-tenant-schema logic is one implementation
 * (Splicewire\Tenancy\Sync\TenantSyncTarget, in laravel-tower-tenancy); a remote/CLI/export target
 * is another. NOTE: no `use` on that class — this foundation never imports up into tower.
 *
 * Because {@see apply()} takes a bare `array $payload` (not a model), a remote payload applies
 * through the identical path as a local one — the same reason {@see Syncable::fromSyncPayload()}
 * is `static` and array-in.
 */
interface SyncTarget
{
    /**
     * Has this source entity already landed at this content version? (lineage/hash diff.)
     *
     * @param  class-string  $class
     */
    public function isCurrent(string $class, string $sourceId, string $sourceHash): bool;

    /**
     * Apply one payload into the target, record lineage, return the landed id.
     *
     * @param  class-string  $class
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    public function apply(string $class, string $sourceId, array $payload, array $config = []): string;
}
