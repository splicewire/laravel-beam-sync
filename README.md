# splicewire/laravel-beam-sync

Generic **sync foundation**. Copies a dependency cone **by value** across shared-nothing
boundaries — tenant schema → tenant schema, or a remote feed → a local store.

## What it owns

- `Splicewire\Sync\Contracts\Syncable` — the 4-method serialise / apply / hash / declare-deps
  contract a model implements to become copyable. **Zero tenancy in the contract.**
- `Splicewire\Sync\Contracts\ConfigAwareSyncPayload` — optional source-side prime port.
- `Splicewire\Sync\Contracts\SyncTarget` / `SyncSource` — the write / ingest ports the engine drives
  through. A tenant target and a remote source are separate implementations of the same seams.
- `Splicewire\Sync\Transit\SyncData` — the byte-stable transit-projection base (a distinct boundary
  from the API/UI DTO — see apocryphon `sync-payload-is-a-distinct-projection-from-the-api-dto`).
- `Splicewire\Sync\Graph\DependencyResolver` — tenancy-free scope-expand + Kahn topo-sort.

## Registries

This package declares one registry:

- [`beam.sync.scaffold-pack.content-kinds`](docs/registries/beam.sync.scaffold-pack.content-kinds.mdx): the
  kinds of content a Scaffold Pack can deliver to a new tenant, and whether each is copied or referenced.

It ships no service provider and registers into no other package's registry. The host that applies Scaffold
Packs binds the registry and fills it.

## Tier

A **foundation** sibling of `laravel-beam`, on the same rung as `rushing/laravel-versioning` and
`schemastud/laravel-data-schemas`. Requires `illuminate/contracts` + `illuminate/support` +
`spatie/laravel-data` + `rushing/php-graphine` + `rushing/php-popcorn` (the graph edge narrowed
from `laravel-graphine` 2026-10-18 — graph-review/A3; the one symbol used is
`Rushing\Graphine\Algorithms\TopologicalSort`, which lives in the framework-free package). **No
beam, no tenancy, no tower, no stancl.** `laravel-beam-taxonomy` and tower's tenancy arm (the
dissolved `laravel-tower-tenancy`, ADR-0162) depend DOWN on it.

## NOT stancl resource-syncing

Do **not** build this on `stancl/tenancy`'s `Syncable` / `ResourceSyncing`. That is a *different
mechanism* — it keeps ONE shared central resource mirrored across tenant DBs on `saved`. beam-sync
copies a whole dependency cone **by value** on a job; identity is not portable, the target mints its
own ids, FKs are remapped through lineage. They are orthogonal.

## Particle bridge (deferred)

Riding the beam `ParticleWriter` for sync-in (authorize + schema-validate + one emit) is an
**optional, beam-gated bridge** — a separate follow-on unit (design U4 / issue 19), not part of this
foundation. The base package loads and works with zero beam.
