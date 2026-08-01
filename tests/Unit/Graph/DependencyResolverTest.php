<?php

use Illuminate\Support\Facades\Log;
use Splicewire\Sync\Graph\DependencyResolver;
use Splicewire\Sync\Tests\Fixtures\FakeAuthor;
use Splicewire\Sync\Tests\Fixtures\FakeBook;
use Splicewire\Sync\Tests\Fixtures\FakeSyncable;

beforeEach(fn () => FakeSyncable::reset());

/** Map the resolver's [class, id] tuples to "Short::id" for terse assertions. */
function keys(array $sorted): array
{
    return array_map(
        static fn (array $node): string => class_basename($node[0]).'::'.$node[1],
        $sorted,
    );
}

it('expands the scope along syncDependencies until stable', function () {
    // b1 -> a1 (book depends on author); scope names only the book.
    FakeSyncable::seed(new FakeAuthor('a1'));
    FakeSyncable::seed(new FakeBook('b1', [FakeAuthor::class => ['a1']]));

    $resolved = (new DependencyResolver)->expandScope([FakeBook::class => ['b1']]);

    expect($resolved[FakeBook::class])->toBe(['b1'])
        ->and($resolved[FakeAuthor::class])->toBe(['a1']);
});

it('orders every dependency before its dependent', function () {
    FakeSyncable::seed(new FakeAuthor('a1'));
    FakeSyncable::seed(new FakeBook('b1', [FakeAuthor::class => ['a1']]));

    $sorted = (new DependencyResolver)->topologicalSort([
        FakeBook::class => ['b1'],
        FakeAuthor::class => ['a1'],
    ]);

    expect(keys($sorted))->toBe(['FakeAuthor::a1', 'FakeBook::b1']);
});

it('orders a diamond so both prerequisites precede the shared dependent', function () {
    // d depends on b and c; b and c each depend on a.
    FakeSyncable::seed(new FakeAuthor('a'));
    FakeSyncable::seed(new FakeBook('b', [FakeAuthor::class => ['a']]));
    FakeSyncable::seed(new FakeBook('c', [FakeAuthor::class => ['a']]));
    FakeSyncable::seed(new FakeBook('d', [FakeBook::class => ['b', 'c']]));

    $sorted = keys((new DependencyResolver)->topologicalSort([
        FakeBook::class => ['d', 'b', 'c'],
        FakeAuthor::class => ['a'],
    ]));

    $pos = array_flip($sorted);
    expect($pos['FakeAuthor::a'])->toBeLessThan($pos['FakeBook::b'])
        ->and($pos['FakeAuthor::a'])->toBeLessThan($pos['FakeBook::c'])
        ->and($pos['FakeBook::b'])->toBeLessThan($pos['FakeBook::d'])
        ->and($pos['FakeBook::c'])->toBeLessThan($pos['FakeBook::d']);
});

it('keeps isolated nodes that declare no dependencies', function () {
    FakeSyncable::seed(new FakeAuthor('lonely'));

    $sorted = (new DependencyResolver)->topologicalSort([FakeAuthor::class => ['lonely']]);

    expect(keys($sorted))->toBe(['FakeAuthor::lonely']);
});

it('degrades on a cycle: warns once and still returns every node', function () {
    Log::shouldReceive('warning')->once();

    // a1 <-> a2 mutually depend — an unorderable cycle.
    FakeSyncable::seed(new FakeAuthor('a1', [FakeAuthor::class => ['a2']]));
    FakeSyncable::seed(new FakeAuthor('a2', [FakeAuthor::class => ['a1']]));

    $sorted = keys((new DependencyResolver)->topologicalSort([
        FakeAuthor::class => ['a1', 'a2'],
    ]));

    expect($sorted)->toHaveCount(2)
        ->and($sorted)->toContain('FakeAuthor::a1')
        ->and($sorted)->toContain('FakeAuthor::a2');
});
