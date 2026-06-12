<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests;

use NxTutors\WhatsAppOnboarding\Bootstrap\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }
}
