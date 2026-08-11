<?php

namespace Splicewire\Sync\ScaffoldPack;

/**
 * How a registered content-kind reaches a target tenant (scaffold-pack-content-kind-registry-
 * federation ticket 02 / ADR-0193): the taxonomy every {@see ScaffoldPackContentRegistry}
 * registration and every consumer's federation-grant model (e.g. Tower's `FederationGrant`)
 * declares itself under. Deliberately name-only here — this package must never import a
 * downstream consumer's classes (`laravel-beam-sync` never requires `splicewire/tower`).
 *
 *  - `Copy`    — cloned by value into the target's own tables at apply time (today's default:
 *    owned, customizable scaffolding a tenant may freely edit post-provision).
 *  - `Pointer` — resolved live by reference against the source/publisher; never duplicated
 *    locally (the living corpus a tenant must never fork — e.g. Fragment).
 *  - `Shadow`  — resolved by reference like `Pointer`, but the resolved artifact is cached
 *    locally (a "shadow" copy) for fast/offline reads; the cache is disposable and re-derivable,
 *    never the system of record (e.g. a federation grant's resolved corpus units).
 *
 * Ranked weakest-federation-first (`Copy` = 0) so a cascade can compare "how much federation"
 * two kinds represent — {@see FederationCascade} downgrades toward `Copy`, never upgrades past it.
 */
enum FederationKind: string
{
    case Copy = 'copy';
    case Pointer = 'pointer';
    case Shadow = 'shadow';

    /** Weakest-federation-first rank, used by {@see FederationCascade} to compare kinds. */
    public function rank(): int
    {
        return match ($this) {
            self::Copy => 0,
            self::Pointer => 1,
            self::Shadow => 2,
        };
    }
}
