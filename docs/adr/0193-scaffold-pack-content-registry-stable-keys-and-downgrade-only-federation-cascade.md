# ADR-0193 — `ScaffoldPackContentRegistry` keys by stable string, not FQCN; federation_kind is a three-way taxonomy resolved by a downgrade-only cascade

Status: accepted. Date: 2026-08-11.

Numbered 0193 — one above the highest existing ADR number found across the family at authoring time
(`find ~/Workspaces/php/packages ~/Herd -path '*/docs/adr/*.md'`, max `0192`, set by
`laravel-beam-market`'s ADR-0192 earlier in this same marketplace build). Per this repo's own
`docs/adr/` convention (repo-local by default; see `splicewire-ecosystem`
`docs/conventions/adr-placement.md`) — not promoted to `splicewire-beam-runbook`: this decision is not
yet proven to govern a second package beyond `laravel-beam-sync`/Tower.

## Context

`Splicewire\Tower\Support\ScaffoldPackApplier::resolveScope()` hardcoded a `$models` array of 12
Eloquent classes it wholesale-copies into a newly provisioned tenant (`Silo`, `JsonSchema`, `Circuit`,
`Entity`, `ContextScope`, `Agent`, `Composition`, `Concept`, `ConceptSynonym`, `ConceptStatus`, `Rule`,
`EvidenceItem`), plus one further content-kind (`Fragment`) that was hardcoded ABSENT from that array
via a comment explaining it federates rather than copies. Every content-kind lived only as an FQCN
literal, with no declared federation behavior beyond "in the array = copied" / "not in the array =
some other unwritten rule the reader has to reconstruct from a comment." `Splicewire\Tower\Models\
Federation\FederationGrant` (the explicit, revocable, scope-granular authorization a publisher tenant
grants a subscriber) carried no `federation_kind` column at all — every grant behaved identically
regardless of what content it fronted.

Two forces make this the moment to formalize: (1) `ADR-0154` dissolved the old `JsonSchema` model onto
the id-addressed `BeamSchema` schema registry, so the 12-entry array needed to change shape anyway —
migrating it as a verbatim FQCN swap would have re-encoded the same "no declared behavior" problem one
model later; (2) the marketplace build's federation mechanics (a self-hosted install must never be
forced into live federation it can't sustain; a publisher must be able to cap how federated its content
may go; an installer should get *some* say without being able to escalate past what's permitted) need a
real, testable taxonomy and cascade — not another comment.

`laravel-beam-sync` is the only legal home for this registry: it never requires `laravel-beam` or
`splicewire/tower` (both forbidden by this package's own `noRequire` topology), while both a beam-tier
package and the tenancy target (Tower) already sit downstream of it. Building the registry inside
either `laravel-beam` or Tower would make it invisible to the other.

## Decision

1. **`ScaffoldPackContentRegistry` keys registrations by a stable string, never the model FQCN.**
   `register(string $key, string $modelClass, FederationKind $federationKind, ?Closure $idResolver,
   ?array $pricing)` — `get()`/`has()` look up by that string. An FQCN is an implementation detail (a
   class can rename or, as `JsonSchema` → `BeamSchema` just proved, get replaced outright); the `key` is
   the durable handle a pack, a pricing table, or an operator UI references across that kind of change
   without a recompile-and-redeploy. `resolveScope()` becomes a pure loop over
   `$registry->copyable()`, keyed by these strings — no hardcoded array survives.
2. **`federation_kind` is a closed three-value enum: `Copy` / `Pointer` / `Shadow`**, ranked
   weakest-federation-first (`Copy` = 0, `Pointer` = 1, `Shadow` = 2 by declaration order; the cascade
   only relies on `Copy` ranking lowest).
   - `Copy` — cloned by value into the target's own tables at apply time. The default for owned,
     customizable scaffolding a tenant may freely edit post-provision (11 of the 12 original entries,
     plus the migrated `BeamSchema` content-schema entry).
   - `Pointer` — resolved live by reference against the source/publisher; never duplicated locally. The
     living corpus a tenant must never fork (`Fragment`'s prior hardcoded absence from the copy array,
     now a real registration instead of an absence).
   - `Shadow` — resolved by reference like `Pointer`, but the resolved artifact is cached locally for
     fast/offline reads; the cache is disposable and re-derivable, never the system of record (matches
     the pre-existing `ShadowCachedTier` concept `GrantService::revoke()` already GC's against).
   Every content-kind registration declares exactly one of these — none is left ungoverned.
3. **`FederationGrant.federation_kind` resolves through a downgrade-only cascade**: content-type
   default → publisher override → installer choice, where the installer may only move the effective
   kind *toward* `Copy` (lower rank), never past the ceiling `publisherOverride ?? contentTypeDefault`
   sets. A **self-hosted target is forced to `Copy` unconditionally**, ahead of every other input — a
   self-hosted node cannot depend on live reachability to a publisher it may never be able to reach
   again. The cascade is a pure static function (`FederationCascade::resolve()`) over enum values and a
   `bool $selfHosted` — no model, no persistence, no tenancy — so it lives in `laravel-beam-sync`
   alongside the taxonomy it operates on; the caller (Tower's `GrantService`) supplies its own
   content-type default and self-hosted detection (`config('beam.licenser.enabled')`, the existing
   self-hosted/SaaS binding switch `laravel-beam-licenser` already established).

## Considered Options

1. **Key the registry by FQCN** (the status quo, just moved into a registry object instead of an
   inline array). Rejected: buys the lookup/enumeration ergonomics of a registry but keeps the exact
   fragility this ADR exists to remove — a future model rename (which just happened once, with
   `JsonSchema`) breaks every registration and every external reference to it (pricing tables, an
   operator UI, a pack manifest) in lockstep.
2. **A two-value `federation_kind` (`copy` / `federated`)**, collapsing `Pointer`/`Shadow` into one
   bucket. Rejected: the codebase already draws this distinction operationally
   (`KnowledgeGroundingDereferencer::ShadowCachedTier` vs. a pure live resolve) — collapsing it in the
   registry's vocabulary would just push the same three-way decision into ad-hoc conditionals at every
   call site instead of naming it once.
3. **Let the installer choice win outright (no ceiling), trusting UI/business logic elsewhere to cap
   it.** Rejected: entitlement/deployment constraints are exactly the kind of invariant that must be
   enforced at the seam that persists the grant, not hoped for upstream — an installer-side bug or a
   future caller that forgets the UI-layer check would otherwise silently over-federate.
4. **Make the self-hosted force a caller-side `if` before calling the cascade, rather than a cascade
   parameter.** Rejected: every current and future caller of `FederationCascade::resolve()` would need
   to remember to re-derive that check correctly; folding it into the cascade itself makes "self-hosted
   always gets `Copy`" impossible to accidentally skip.

## Consequences

- Every future content-kind (Tower's own paid kinds included, e.g. `Conduit`/`RunnerTransform` via the
  registry's optional `pricing` block) registers through the same seam, with the same three-way
  taxonomy — there is no longer a second, undeclared way for a content-kind to exist in a Scaffold Pack.
- A content-kind's registry `key` is now part of the family's stable vocabulary (pricing tables, an
  operator UI, or a future pack manifest may reference it directly) — renaming a `key` post-adoption is
  the kind of break this ADR was written to prevent for FQCNs; treat keys with the same rename
  discipline a public API would get.
- The cascade's ranking (`Copy` < `Pointer` < `Shadow`) is now load-bearing: adding a fourth
  `federation_kind` requires deciding where it ranks relative to these three, not just adding a case.
- This ADR governs `laravel-beam-sync` and its Tower consumer only. If a second, unrelated package
  independently needs the same registry/cascade shape, that is the trigger to promote this decision to
  `splicewire-beam-runbook` — not before.
