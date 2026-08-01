<?php

namespace Splicewire\Sync\Transit;

use Spatie\LaravelData\Data;
use Splicewire\Sync\Contracts\Syncable;

/**
 * Base class for a sync **transit** payload contract.
 *
 * A `*SyncData` types the *shape of the array a {@see Syncable} puts on the wire* — NOT
 * the entity's API/UI representation. The two are distinct boundary projections and must never be
 * unified: see the apocryphon `sync-payload-is-a-distinct-projection-from-the-api-dto`. Concretely
 * a SyncData:
 *
 *  - carries **no local `id`** — the target mints its own; identity is not portable across
 *    the shared-nothing schema boundary;
 *  - carries *source* ids for remapped FKs (`sourceGoldenScopeId`, `sourceParentId`, …) that
 *    {@see Syncable::fromSyncPayload()} resolves through lineage;
 *  - carries copyable transit-only relations (verbatim embedding vectors, RDF triples, silo
 *    pivots, media sidecar) that no API DTO would model; and
 *  - carries protocol fields (`_hash`, `_media_*`).
 *
 * It is a *typed intermediate*, not a new transport: `Syncable::toSyncPayload()` still returns a
 * bare `array` (`$this->toSyncData()->toArray()`) and the engine, hashing, and phased transit are
 * unchanged. Serialized keys are pinned with `#[MapName]` so the wire bytes are byte-for-byte what
 * the hand-built arrays emitted before this refactor (guarded by the byte-stability test at
 * the consuming app's `tests/Feature/Sync/SyncPayloadByteStabilityTest.php`).
 *
 * SyncData classes are **backend-only**: they carry no `#[TypeScript]`/`#[AdminResource]`
 * attribute, so an app's attributed-class transformer never leaks them into the UI type manifest.
 */
abstract class SyncData extends Data {}
