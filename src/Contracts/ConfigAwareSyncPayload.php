<?php

namespace Splicewire\Sync\Contracts;

/**
 * A Syncable whose SOURCE-side serialisation depends on the sync run's `$config`.
 *
 * {@see Syncable::toSyncPayload()} is deliberately arg-less — the vast majority of entities serialise
 * the same bytes regardless of who/why. But a few need the run config at serialise time: the tenant-
 * provisioning path carries flags (e.g. `include_version_history`) decided when the pack is applied,
 * and those must reach the SOURCE read in Phase 1 — where `fromSyncPayload($payload, $config)`'s config
 * is not yet in play (that is Phase 2, the target write). The engine primes such entities right before
 * calling {@see Syncable::toSyncPayload()}, so the entity keeps its no-arg contract while still seeing
 * the config. Entities that don't implement this are never primed and serialise exactly as before.
 *
 * version-history-on-provision ticket 03 — carry frozen version snapshots in the payload for `demo` only.
 */
interface ConfigAwareSyncPayload
{
    /**
     * Receive the sync run's `$config` on the SOURCE side, immediately before {@see Syncable::toSyncPayload()}
     * serialises this entity. Transient — the entity stashes what it needs; nothing is persisted.
     *
     * @param  array<string, mixed>  $config
     */
    public function primeSyncPayload(array $config): void;
}
