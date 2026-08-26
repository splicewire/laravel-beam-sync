<?php

namespace Splicewire\Sync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Splicewire\Sync\Transit\SyncData;

/**
 * Base test case — boots a minimal Laravel container so facades (Log) resolve.
 *
 * beam-sync ships no service provider of its OWN, but "no provider of its own" was long written
 * here as "no providers at all: the container is enough", and that was measurably false. Testbench
 * does not auto-discover — a package harness boots exactly what {@see getPackageProviders()} names,
 * while `src/` freely imports anything it can autoload. {@see SyncData}
 * extends `Spatie\LaravelData\Data`, so with an empty provider list `config('data')` was NULL in
 * this suite and every laravel-data entry point fataled (`Trying to access array offset on null`)
 * instead of failing. Measured in `splicewire/tower` under api-surface-coherence ticket 84 and
 * swept across the sibling packages under ticket 85; the null was confirmed here before this fix.
 *
 * A package's own dependency-of-a-dependency is exactly what makes the omission easy to miss, which
 * is why the pin lives in `tests/Unit/Transit/SyncDataIsExecutableTest.php` rather than in a comment
 * and why `Rushing\Surgeon\Conformance\HarnessProviderAudit` checks the shape fleet-wide.
 */
class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Booting LaravelDataServiceProvider alone would be a FALSE GREEN. The package ships
        // `name_mapping_strategy.input => null`, but the only host that runs this code
        // (`~/Herd/splicewire-app/config/data.php`) sets it to CamelCaseMapper — the ONLY semantic
        // delta between the two files. A DTO whose properties are snake_case hydrates fine under
        // testbench defaults and silently stops mapping under the host's mapper. The harness
        // mirrors the host, not the package default.
        $app['config']->set('data.name_mapping_strategy.input', CamelCaseMapper::class);

        // Structure caching points at `app_path('Data')` by default, which does not exist here, and
        // a cached reflection analysis carried across runs is exactly what a harness should not have.
        $app['config']->set('data.structure_caching.enabled', false);
    }
}
