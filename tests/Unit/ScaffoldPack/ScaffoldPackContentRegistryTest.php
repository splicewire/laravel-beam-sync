<?php

use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\Registry;
use Splicewire\Sync\ScaffoldPack\ContentKindRegistration;
use Splicewire\Sync\ScaffoldPack\FederationKind;
use Splicewire\Sync\ScaffoldPack\ScaffoldPackContentRegistry;
use Splicewire\Sync\Tests\Fixtures\FakeAuthor;
use Splicewire\Sync\Tests\Fixtures\FakeBook;

it('registers and looks up a content-kind by its stable string key, not the FQCN', function () {
    $registry = new ScaffoldPackContentRegistry;

    $registry->register('author', FakeAuthor::class, FederationKind::Copy);

    expect($registry->has('author'))->toBeTrue()
        ->and($registry->has('book'))->toBeFalse()
        ->and($registry->get('author')->modelClass)->toBe(FakeAuthor::class)
        ->and($registry->get('author')->federationKind)->toBe(FederationKind::Copy);
});

it('throws on lookup of an unregistered key', function () {
    (new ScaffoldPackContentRegistry)->get('missing');
})->throws(InvalidArgumentException::class, 'No ScaffoldPackContentRegistry registration for key [missing].');

it('lists every registered key', function () {
    $registry = new ScaffoldPackContentRegistry;
    $registry->register('author', FakeAuthor::class, FederationKind::Copy);
    $registry->register('book', FakeBook::class, FederationKind::Pointer);

    // `keys()` is the kernel's contract now (absolute `RegistryKey`s); the relative string list a
    // caller wants is `kinds()`.
    expect($registry->kinds())->toBe(['author', 'book']);
});

it('filters to only copy-kind registrations', function () {
    $registry = new ScaffoldPackContentRegistry;
    $registry->register('author', FakeAuthor::class, FederationKind::Copy);
    $registry->register('book', FakeBook::class, FederationKind::Pointer);

    expect(array_keys($registry->copyable()))->toBe(['author']);
});

it('resolves ids through a custom resolver when one is registered', function () {
    $registry = new ScaffoldPackContentRegistry;
    $registry->register('author', FakeAuthor::class, FederationKind::Copy, fn () => ['a1', 'a2']);

    expect($registry->get('author')->resolveIds())->toBe(['a1', 'a2']);
});

it('describes every registration for the manifest command', function () {
    $registry = new ScaffoldPackContentRegistry;
    $registry->register('author', FakeAuthor::class, FederationKind::Copy);
    $registry->register('fragment', FakeBook::class, FederationKind::Pointer);

    expect($registry->describe())->toBe([
        ['key' => 'author', 'model' => FakeAuthor::class, 'federation_kind' => 'copy', 'priced' => false],
        ['key' => 'fragment', 'model' => FakeBook::class, 'federation_kind' => 'pointer', 'priced' => false],
    ]);
});

// ── registry-kernel ticket 38: the popcorn contract, over the same entries ────────────────────

it('conforms to the kernel contract and stamps its declared root onto every key', function () {
    $registry = new ScaffoldPackContentRegistry;
    $registry->register('author', FakeAuthor::class, FederationKind::Copy);

    expect($registry)->toBeInstanceOf(Registry::class)
        ->and($registry)->toBeInstanceOf(Gated::class)
        ->and((string) $registry->keys()[0])->toBe('beam.sync.scaffold-pack.content-kinds.author')
        // Relative in, absolute out — both spellings reach the same entry.
        ->and($registry->has('author'))->toBeTrue()
        ->and($registry->has('beam.sync.scaffold-pack.content-kinds.author'))->toBeTrue()
        ->and($registry->resolve('author')->modelClass)->toBe(FakeAuthor::class);
});

it('accepts a self-keying ContentKindRegistration through the contract door', function () {
    $registry = new ScaffoldPackContentRegistry;

    $registry->register(new ContentKindRegistration('author', FakeAuthor::class, FederationKind::Copy));

    expect($registry->kinds())->toBe(['author'])
        ->and($registry->get('author')->federationKind)->toBe(FederationKind::Copy);
});

it('throws the kernel RegistryMiss from the contract door, and its own message from get()', function () {
    // A13: `resolve()` owes the contract's exception; `get()` owes its callers the message that
    // names the registry and the key. Both, not one.
    $registry = new ScaffoldPackContentRegistry;

    expect(fn () => $registry->resolve('missing'))->toThrow(RegistryMiss::class);
    expect(fn () => $registry->get('missing'))
        ->toThrow(InvalidArgumentException::class, 'No ScaffoldPackContentRegistry registration for key [missing].');
});

it('answers false rather than throwing for a key outside the key grammar', function () {
    expect((new ScaffoldPackContentRegistry)->has('Not A Key@2'))->toBeFalse();
});

it('keeps RunAll registration order, and a re-registration supersedes AND appends', function () {
    // ⚠️ RunAll makes order load-bearing: Tower walks `copyable()` to build the wholesale-copy
    // scope. A PHP array assignment held the original slot; superseding MOVES the entry to the end.
    $registry = new ScaffoldPackContentRegistry;
    $registry->register('author', FakeAuthor::class, FederationKind::Copy);
    $registry->register('book', FakeBook::class, FederationKind::Copy);

    expect($registry->kinds())->toBe(['author', 'book'])
        ->and(array_keys($registry->copyable()))->toBe(['author', 'book']);

    $registry->register('author', FakeBook::class, FederationKind::Copy);

    expect($registry->kinds())->toBe(['book', 'author'])
        ->and($registry->get('author')->modelClass)->toBe(FakeBook::class)
        ->and(array_column($registry->describe(), 'key'))->toBe(['book', 'author']);
});
