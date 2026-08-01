<?php

namespace Splicewire\Sync\Contracts;

/**
 * Resolves a *source* entity identity to the id the same entity landed under in the CURRENT
 * (target) context — the FK-remap seam a {@see Syncable::fromSyncPayload()} calls THROUGH when it
 * needs to rewrite a copied foreign key (a parent silo, a golden scope, …) to its target-local id.
 *
 * Identity is not portable across the shared-nothing sync boundary: the target mints its own ids,
 * so a payload carries the *source* FK (`source_parent_id`, …) and the applying model resolves it
 * forward. WHERE that mapping is recorded is a target concern — a tenant target keys it on a
 * lineage table; a remote/CLI target might key it on an export manifest — so the model must never
 * name the concrete store. It receives a resolver through `$config` under {@see self::CONFIG_KEY}
 * (supplied by the {@see SyncTarget}) and, when none is present (headless / no-remap apply), leaves
 * the FK unresolved.
 *
 * Introduced in tower-api-dissolution issue 17 U4a so the beam taxonomy models can carry their own
 * `fromSyncPayload` without depending UP on tower-tenancy's `TenantSyncLineage`. The deeper
 * Particle-pipeline sync-in bridge (routing apply through `ParticleWriter`) is deferred to issue 19.
 */
interface SyncLineageResolver
{
    /**
     * The `$config` key under which a {@see SyncTarget} passes its resolver into
     * `Syncable::fromSyncPayload($payload, $config)`.
     */
    public const CONFIG_KEY = 'sync.lineage_resolver';

    /**
     * The target-local id the given source entity landed under, or null if it has not yet landed
     * (or this target does not track lineage). The applying model treats null as "leave the FK
     * unresolved".
     *
     * @param  class-string  $class  the syncable class the source id belongs to
     * @param  string  $sourceId  the entity's identity in the SOURCE context
     */
    public function resolveTargetId(string $class, string $sourceId): ?string;
}
