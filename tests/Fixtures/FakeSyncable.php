<?php

namespace Splicewire\Sync\Tests\Fixtures;

use Splicewire\Sync\Contracts\Syncable;

/**
 * An in-memory Syncable fixture — no Eloquent, no database. DependencyResolver only
 * ever touches `$class::find($id)` and `->syncDependencies()`, so a static registry
 * keyed by concrete class is enough to exercise the scope-expand + topo-sort logic.
 *
 * Concrete subclasses (FakeAuthor, FakeBook, ...) give distinct class-strings so the
 * resolver can build cross-"model" dependency edges.
 */
class FakeSyncable implements Syncable
{
    /** @var array<class-string, array<string, self>> */
    protected static array $registry = [];

    /**
     * @param  array<class-string, list<string>>  $dependencies  [class => [id, ...]] that must precede this one
     */
    public function __construct(
        public string $id,
        public array $dependencies = [],
    ) {}

    /** Register an instance so `find()` can return it. */
    public static function seed(self $node): void
    {
        static::$registry[$node::class][$node->id] = $node;
    }

    public static function find(string $id): ?static
    {
        return static::$registry[static::class][$id] ?? null;
    }

    /** Clear every registered fixture between tests. */
    public static function reset(): void
    {
        static::$registry = [];
    }

    public function syncDependencies(): array
    {
        return $this->dependencies;
    }

    public function toSyncPayload(): array
    {
        return ['id' => $this->id];
    }

    public static function fromSyncPayload(array $data, array $config = []): static
    {
        return new static($data['id']);
    }

    public function syncHash(): string
    {
        return md5(static::class.$this->id);
    }
}
