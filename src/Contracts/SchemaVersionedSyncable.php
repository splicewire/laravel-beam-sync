<?php

namespace Splicewire\Sync\Contracts;

/**
 * An optional refinement of {@see Syncable} for a model whose sync payload is a
 * VERSIONED SYSTEM SCHEMA — so a schema-driven target can reconcile-on-apply
 * (migrate-on-read the incoming payload against the current shape before persisting).
 *
 * Kept a SEPARATE, optional contract (not folded into {@see Syncable}) so beam-sync
 * stays a bare foundation: a plain syncable that has no versioned schema simply does
 * not implement this, and the tenant-sync-in bridge treats its payload as a total
 * "no reconcile" no-op — identical to the pre-issue-19 behaviour.
 *
 * The reconcile machinery itself (the ladder, the registry, the target resolver) is
 * beam's — resolved by the tower-tenancy sync-in bridge from the container, NOT a
 * dependency of beam-sync. This contract only NAMES the record type the bridge should
 * reconcile against; it pulls in no beam types.
 */
interface SchemaVersionedSyncable extends Syncable
{
    /**
     * The canonical, versioned schema record type to reconcile an incoming payload
     * against — a `class-string` of the `Data & SchemaIdentity` DTO that owns this
     * model's schema `$id` (e.g. `TagData::class`). The bridge passes this to the beam
     * reconciler as its `recordType`, so the live class projects the current target.
     *
     * @return class-string
     */
    public static function syncSchemaRecordType(): string;
}
