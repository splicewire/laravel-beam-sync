<?php

namespace Splicewire\Sync\ScaffoldPack;

use InvalidArgumentException;
use Splicewire\Sync\Graph\DependencyResolver;

/**
 * The registration + lookup seam for every content-kind a Scaffold Pack may deliver
 * (scaffold-pack-content-kind-registry-federation ticket 02, ADR-0193). Lives in
 * `laravel-beam-sync` (not `laravel-beam` or Tower) purely by topology: this is the one package
 * both a beam-taxonomy consumer and the tenancy target (Tower) sit downstream of, so it is the
 * only legal home for a seam either side needs to reach without either reaching UP into the
 * other (`laravel-beam-sync` never requires `laravel-beam` or `splicewire/tower`).
 *
 * Registrations key by a STABLE STRING, never the model FQCN: an FQCN is an implementation
 * detail (a class can move, get renamed, or — as with the former `JsonSchema` model dissolving
 * onto `BeamSchema` per ADR-0154 — get replaced outright) while the `key` is the durable
 * cross-version handle a pack, a pricing table, or an operator UI can reference without ever
 * needing a recompile-and-redeploy to follow a class rename.
 *
 * Pure registry (no I/O, no tenancy): `register()`/`get()`/`has()`/`all()` are synchronous,
 * in-memory operations. A consumer resolves ids (`ContentKindRegistration::resolveIds()`) inside
 * whatever read context (tenant schema, remote feed) it already runs in — same discipline as
 * {@see DependencyResolver}.
 */
class ScaffoldPackContentRegistry
{
    /** @var array<string, ContentKindRegistration> */
    private array $registrations = [];

    /**
     * @param  class-string  $modelClass
     * @param  (\Closure(): list<string>)|null  $idResolver
     * @param  array<string, mixed>|null  $pricing
     */
    public function register(
        string $key,
        string $modelClass,
        FederationKind $federationKind,
        ?\Closure $idResolver = null,
        ?array $pricing = null,
    ): ContentKindRegistration {
        $registration = new ContentKindRegistration($key, $modelClass, $federationKind, $idResolver, $pricing);

        $this->registrations[$key] = $registration;

        return $registration;
    }

    public function has(string $key): bool
    {
        return isset($this->registrations[$key]);
    }

    public function get(string $key): ContentKindRegistration
    {
        return $this->registrations[$key]
            ?? throw new InvalidArgumentException("No ScaffoldPackContentRegistry registration for key [{$key}].");
    }

    /** @return array<string, ContentKindRegistration> */
    public function all(): array
    {
        return $this->registrations;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->registrations);
    }

    /** Every registration declaring {@see FederationKind::Copy} — the wholesale-clone set. */
    public function copyable(): array
    {
        return array_filter(
            $this->registrations,
            static fn (ContentKindRegistration $r) => $r->federationKind === FederationKind::Copy,
        );
    }

    /** @return list<array{key: string, model: class-string, federation_kind: string, priced: bool}> */
    public function describe(): array
    {
        return array_values(array_map(
            static fn (ContentKindRegistration $r) => $r->describe(),
            $this->registrations,
        ));
    }
}
