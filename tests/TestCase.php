<?php

namespace Splicewire\Sync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case — boots a minimal Laravel container so facades (Log) resolve.
 * beam-sync needs no service provider of its own; the container is enough.
 */
class TestCase extends Orchestra
{
    //
}
