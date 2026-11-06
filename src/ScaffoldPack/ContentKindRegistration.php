<?php

namespace Splicewire\Sync\ScaffoldPack;

use Closure;

/**
 * One registered content-kind: a stable string `key` (never the FQCN — see
 * {@see ScaffoldPackContentRegistry}'s class doc for why), the Syncable model it addresses, its
 * documented default {@see FederationKind}, and an optional `$idResolver` for a content-kind whose
 * scope-relevant rows are a FILTERED subset of the model's table (e.g. `BeamSchema` rows under the
 * `content-schema/` stem namespace — every other schema kind in that shared table is out of scope).
 * Defaults to every row of `$modelClass` when no resolver is given.
 *
 * `$resource` names the particle resource a kind's rows are BROWSED through in the source tenant (owner-review-20261102
 * O10, "Save this workspace as a pack"): a pack's author picks All, None or Some of each kind, and Some lists that
 * resource's records. The key and the resource are different vocabularies (tower's `entity` kind is the `declarations`
 * resource), and one model may back several resources, so the registrant states it rather than a reader deriving it.
 * Null means the kind has no resource of its own and travels all-or-none with the kind that owns it.
 */
final class ContentKindRegistration
{
    /**
     * @param  class-string  $modelClass
     * @param  (Closure(): list<string>)|null  $idResolver
     * @param  array<string, mixed>|null  $pricing
     * @param  string|null  $resource  the particle resource key the kind's rows are browsed through, or null
     */
    public function __construct(
        public readonly string $key,
        public readonly string $modelClass,
        public readonly FederationKind $federationKind,
        public readonly ?Closure $idResolver = null,
        public readonly ?array $pricing = null,
        public readonly ?string $resource = null,
    ) {}

    /** The ids in scope for this content-kind, run inside whatever context resolves the model. */
    public function resolveIds(): array
    {
        if ($this->idResolver !== null) {
            return ($this->idResolver)();
        }

        $model = $this->modelClass;

        return $model::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }

    /** @return array{key: string, model: class-string, federation_kind: string, priced: bool, resource: string|null} */
    public function describe(): array
    {
        return [
            'key' => $this->key,
            'model' => $this->modelClass,
            'federation_kind' => $this->federationKind->value,
            'priced' => $this->pricing !== null,
            'resource' => $this->resource,
        ];
    }
}
