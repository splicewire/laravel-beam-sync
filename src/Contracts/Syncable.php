<?php

namespace Splicewire\Sync\Contracts;

/**
 * A model that can be copied BY VALUE across a shared-nothing boundary (tenant schema to
 * tenant schema, or a remote feed to a local store). Pure serialise / apply / hash / declare-deps —
 * ZERO tenancy in the contract. This is the only piece a syncable model needs to implement.
 *
 * Extracted DOWN into splicewire/laravel-beam-sync (issue 17 P1). The old FQCN
 * `Splicewire\Tower\Tenancy\Sync\Syncable` remains a back-compat class_alias for one release.
 */
interface Syncable
{
    /**
     * Serialise the entity (and its copyable relations) for transport across schema boundaries.
     */
    public function toSyncPayload(): array;

    /**
     * Write the entity into the current (target) context.
     * $config may carry runtime overrides, e.g. ['user_id' => '...'].
     */
    public static function fromSyncPayload(array $data, array $config = []): static;

    /**
     * Return a stable hash of the entity's content for change detection.
     */
    public function syncHash(): string;

    /**
     * Return [ModelClass => [id, ...]] of entities that must exist in the target
     * before this one is written. Inspects actual entity data, not static type lists.
     *
     * @return array<class-string, list<string>>
     */
    public function syncDependencies(): array;
}
