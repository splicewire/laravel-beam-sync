<?php

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

    expect($registry->keys())->toBe(['author', 'book']);
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
