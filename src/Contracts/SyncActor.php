<?php

namespace Splicewire\Sync\Contracts;

/**
 * The **sync principal** — the actor a synced write is applied AS.
 *
 * A sync apply has no request-user: it runs in a background job / CLI / remote-feed ingest, far from
 * any authenticated HTTP context. But the deeper "sync-in rides the Particle write pipeline" bridge
 * (tower-api-dissolution issue 19) routes apply through beam's `ParticleWriter`, whose `WriteGate` is
 * **deny-by-default and demands an actor** — a null actor is a guest, and a guest is refused. So sync
 * needs a *system* principal the write gate can recognize and authorize.
 *
 * `SyncActor` is that marker. It carries no methods — its VALUE is the type: a gate wired for the sync
 * path (a `SyncWriteGate`) authorizes a subject IFF the actor implements this interface, so:
 *
 *  - a sync apply carrying a recognized `SyncActor` proceeds (authorization-on-sync satisfied);
 *  - an apply carrying no actor, or a non-`SyncActor` actor, is **refused** — the new authz gate the
 *    bare `fromSyncPayload → save` path never had.
 *
 * Kept as a bare marker in the sync FOUNDATION (no beam / Illuminate-auth dependency) so the contract
 * ships with the base and any target — tenant, remote, CLI — mints a principal the gate trusts. A
 * concrete system principal ({@see SystemSyncActor}) is provided; a host may supply its own (e.g. a
 * scoped service account) as long as it implements this interface.
 */
interface SyncActor
{
    /**
     * A stable, human-readable identifier for the sync principal — the name that shows up in an audit
     * of "who applied this synced write". Not an authorization input (the type IS the authorization);
     * purely for lineage / logging.
     */
    public function syncActorName(): string;
}
