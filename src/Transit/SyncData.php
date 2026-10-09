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
 *  - carries transit fields (`hash`, `mediaDisk`, `mediaPath`, …) in the estate's camelCase wire
 *    vocabulary.
 *
 * It is a *typed intermediate*, not a new transport: `Syncable::toSyncPayload()` still returns a
 * bare `array` (`$this->toSyncData()->toArray()`) and the engine, hashing, and phased transit are
 * unchanged. Data properties use the published camelCase spelling directly; database columns remain
 * snake_case behind each model's import/export seam. The consuming app guards the exact projection in
 * `tests/Feature/Sync/SyncPayloadByteStabilityTest.php`.
 *
 * SyncData classes are **backend-only**: they carry no `#[TypeScript]`/`#[ParticleResource]`
 * attribute, so an app's attributed-class transformer never leaks them into the UI type manifest.
 */
abstract class SyncData extends Data
{
    /**
     * The optional protocol key carrying the SOURCE record's canonical schema `$id`
     * (relative `<name>/<version>` form, e.g. `taxonomy/tag/1`) so a schema-driven
     * target can reconcile-on-apply — migrate the incoming payload against the current
     * schema before it persists. Purely additive: a payload that omits it (every
     * pre-existing wire shape) reconciles as a total no-op. NEVER folded into `hash`,
     * so adding it does not shift lineage diffing.
     */
    public const SCHEMA_KEY = 'schemaId';
}
