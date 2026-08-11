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
 */
final class ContentKindRegistration
{
    /**
     * @param  class-string  $modelClass
     * @param  (Closure(): list<string>)|null  $idResolver
     * @param  array<string, mixed>|null  $pricing
     */
    public function __construct(
        public readonly string $key,
        public readonly string $modelClass,
        public readonly FederationKind $federationKind,
        public readonly ?Closure $idResolver = null,
        public readonly ?array $pricing = null,
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

    /** @return array{key: string, model: class-string, federation_kind: string, priced: bool} */
    public function describe(): array
    {
        return [
            'key' => $this->key,
            'model' => $this->modelClass,
            'federation_kind' => $this->federationKind->value,
            'priced' => $this->pricing !== null,
        ];
    }
}
