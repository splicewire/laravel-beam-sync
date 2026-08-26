<?php

use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\LaravelData\Support\DataConfig;
use Splicewire\Sync\Transit\SyncData;

/**
 * The executability gate for beam-sync's transit DTOs (api-surface-coherence ticket 85).
 *
 * Testbench does not auto-discover: a package harness boots exactly what `getPackageProviders()`
 * names. This package's `tests/TestCase.php` named nothing at all — its docblock asserted that
 * "the container is enough" — so `config('data')` was NULL inside this suite and every laravel-data
 * entry point ({@see Data::validateAndCreate()},
 * {@see Data::from()}) fataled with `Trying to access array offset on null`
 * rather than failing. Measured first in `splicewire/tower` under ticket 84; this package shares
 * the shape through {@see SyncData}.
 *
 * So this walks every laravel-data class declared under `src/` and executes the two halves of the
 * hydration path that run before a payload is ever touched:
 *
 *  1. `DataConfig::getDataClass()` — the full reflection analysis: every property's type, every
 *     attribute instantiated, casts / mappers / nested Data classes resolved.
 *  2. `::getValidationRules([])` — the rule inferrers plus any hand-written `rules()`, projected
 *     through the input name mapper.
 *
 * Neither needs a fixture payload, which is what lets it be data-driven. Abstract bases are
 * INCLUDED deliberately: `SyncData` is abstract and is presently this package's entire
 * laravel-data surface, and its `#[MapName]`-pinned wire bytes are exactly what an unanalysable
 * base would silently take down.
 */
function beamSyncDataClasses(): array
{
    $root = dirname(__DIR__, 3).'/src';

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    $classes = [];

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (! str_ends_with($path, '.php')) {
            continue;
        }

        // Namespace is the path under src/, PSR-4'd onto Splicewire\Sync\.
        $relative = substr($path, strlen($root) + 1, -4);
        $class = 'Splicewire\\Sync\\'.str_replace('/', '\\', $relative);

        if (! class_exists($class)) {
            continue;
        }

        if (! is_subclass_of($class, BaseData::class)) {
            continue;
        }

        $classes[$class] = [$class];
    }

    ksort($classes);

    return $classes;
}

it('has the laravel-data provider booted, mirroring the host config', function () {
    // Guards the fix itself. Without the provider `config('data')` is null and every case below
    // fatals rather than fails, which is a much worse signal.
    expect(config('data'))->not->toBeNull();

    // And guards against the FALSE GREEN: the package ships `name_mapping_strategy.input => null`,
    // and only the host (`~/Herd/splicewire-app/config/data.php`) sets the mapper. A DTO that
    // hydrates fine under the package default can silently stop mapping under the host's.
    expect(config('data.name_mapping_strategy.input'))
        ->toBe(CamelCaseMapper::class);
});

it('finds beam-sync laravel-data classes to check', function () {
    // A discovery bug that silently found zero classes would make every case below vacuous.
    expect(count(beamSyncDataClasses()))->toBeGreaterThan(0);
});

it('can analyse and derive validation rules for every declared laravel-data class', function (string $class) {
    $dataClass = app(DataConfig::class)->getDataClass($class);

    expect($dataClass->name)->toBe($class);

    $rules = $class::getValidationRules([]);

    expect($rules)->toBeArray();
})->with(beamSyncDataClasses());
