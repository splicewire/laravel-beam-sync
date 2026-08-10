> You are in **splicewire/laravel-beam-sync** — the generic sync foundation for the beam family.

A Laravel package that copies a dependency cone **by value** across shared-nothing boundaries
(tenant schema → tenant schema, or a remote feed → a local store). Ships the `Syncable` /
`SyncTarget` / `SyncSource` contracts, the byte-stable `SyncData` transit projection, and a
tenancy-free `DependencyResolver` (scope-expand + Kahn topo-sort). Zero tenancy baked into the
contracts themselves.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
