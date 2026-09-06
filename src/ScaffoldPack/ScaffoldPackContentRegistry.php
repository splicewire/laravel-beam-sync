<?php

namespace Splicewire\Sync\ScaffoldPack;

use Closure;
use InvalidArgumentException;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
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
 *
 * ## On the popcorn kernel (registry-kernel ticket 38)
 *
 * The `[key => ContentKindRegistration]` array is a {@see BasicRegistry} held as a field, so
 * registration order — observable because Tower walks
 * `copyable()` to build a wholesale-copy scope — is the kernel's documented guarantee rather than
 * a PHP array's implicit one.
 *
 * Three notes for anyone reading a diff against the pre-kernel shape:
 *
 * - **`register()` is widened, never renamed.** The kernel's spelling is
 *   `register($key, $entry, $by, $ability)`; this class's is
 *   `register($key, $modelClass, $federationKind, $idResolver, $pricing)`. The union types below
 *   accept both and a `FederationKind` in the third slot is what tells them apart, so every one of
 *   Tower's thirteen historical calls keeps working verbatim. It now returns `static` (the contract
 *   is covariant on `static` and `ContentKindRegistration` is not a subtype of it); no caller in the
 *   estate ever used the returned registration, and {@see get()} answers for the ones that might.
 * - **`keys()` was RENAMED to {@see kinds()}.** The contract spells `keys(): array` and means
 *   *every key, absolute, as a `RegistryKey`* — the index and the conformance audits read it that
 *   way. This class had spent the name on the relative string list, which is now `kinds()`.
 * - **`get()` keeps its own exception.** The contract requires `resolve()` to throw `RegistryMiss`
 *   and it does; it does not require the port's sugar to, and this message names the registry and
 *   the key a caller actually passed. Keeping it cost zero consumer edits.
 */
#[IsRegistry(
    root: 'beam.sync.scaffold-pack.content-kinds',
    entryType: ContentKindRegistration::class,
    onDuplicate: OnDuplicate::Supersede,
    description: 'the content-kinds a Scaffold Pack may deliver, each with a federation_kind (copy/pointer/shadow). Declared HERE, on the owning class, though `splicewire/tower` is what binds, fills and describes it — beam-sync ships no service provider of its own. Moving the binding and the describe() CALL into this package is registry-kernel ticket 22\'s; the declaration has only ever had one legal home.',
    order: 70,
)]
class ScaffoldPackContentRegistry implements Gated, Registry
{
    /** @var BasicRegistry<ContentKindRegistration> */
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Register a content-kind.
     *
     * Widened CONTRAVARIANTLY from the contract so both spellings live at one door — see the class
     * docblock. A {@see FederationKind} in the third slot means the historical domain call; a
     * {@see ContentKindRegistration} as `$key` or `$entry` means the contract call, self-keying off
     * {@see ContentKindRegistration::$key}.
     *
     * @param  class-string|ContentKindRegistration|mixed  $entry  the model class (domain form)
     * @param  (Closure(): list<string>)|string|null  $ability  the id resolver (domain form)
     * @param  array<string, mixed>|null  $pricing
     */
    public function register(
        RegistryKey|string|ContentKindRegistration $key,
        mixed $entry = null,
        FederationKind|string|null $by = null,
        Closure|string|null $ability = null,
        ?array $pricing = null,
    ): static {
        if ($key instanceof ContentKindRegistration) {
            $entry = $key;
            $key = $key->key;
        }

        if ($by instanceof FederationKind) {
            $entry = new ContentKindRegistration(
                (string) $key,
                (string) $entry,
                $by,
                $ability instanceof Closure ? $ability : null,
                $pricing,
            );
            $by = null;
            $ability = null;
        }

        $this->entries->register($key, $entry, $by, is_string($ability) ? $ability : null);

        return $this;
    }

    /**
     * Whether a content-kind is registered.
     *
     * Guarded on {@see Key::tryParse()} because a content-kind key travels through pack manifests
     * and operator input: a spelling outside the key grammar answered `false` here and would now
     * throw `InvalidRegistryKey`. Unregistered is unregistered.
     */
    public function has(RegistryKey|string $key): bool
    {
        if (is_string($key) && Key::tryParse($key) === null) {
            return false;
        }

        return $this->entries->has($key);
    }

    /**
     * The registration for a content-kind, or a package-local throw naming the registry and the key.
     * {@see resolve()} is the contract's door and throws the kernel's `RegistryMiss`; this one keeps
     * the message its callers have always read.
     */
    public function get(string $key): ContentKindRegistration
    {
        return ($this->has($key) ? $this->entries->tryResolve($key) : null)
            ?? throw new InvalidArgumentException("No ScaffoldPackContentRegistry registration for key [{$key}].");
    }

    /** @return array<string, ContentKindRegistration> every registration, in registration order */
    public function all(): array
    {
        $all = [];

        foreach ($this->kinds() as $kind) {
            $all[$kind] = $this->entries->resolve($kind);
        }

        return $all;
    }

    /**
     * @return list<string> the registered content-kind keys, as the registrants spelled them —
     *                      `relativeKeys()`, because keys go relative in and absolute out (20 D2).
     */
    public function kinds(): array
    {
        return $this->entries->relativeKeys();
    }

    /** Every registration declaring {@see FederationKind::Copy} — the wholesale-clone set. */
    public function copyable(): array
    {
        return array_filter(
            $this->all(),
            static fn (ContentKindRegistration $r) => $r->federationKind === FederationKind::Copy,
        );
    }

    /** @return list<array{key: string, model: class-string, federation_kind: string, priced: bool}> */
    public function describe(): array
    {
        return array_values(array_map(
            static fn (ContentKindRegistration $r) => $r->describe(),
            $this->all(),
        ));
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    public function keys(): array
    {
        return $this->entries->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }
}
