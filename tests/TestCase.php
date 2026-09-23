<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstNonTestingDatabase();
    }

    /**
     * RefreshDatabase will migrate:fresh whatever database it is pointed at, so a missing
     * or misconfigured .env.testing would silently wipe the development database. Refuse
     * to run unless the target database is clearly marked as a test database.
     */
    private function guardAgainstNonTestingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! str_ends_with((string) $database, '_testing')) {
            $this->fail(
                "Refusing to run tests against the '{$database}' database — expected a name "
                ."ending in '_testing'. Copy .env.testing.example to .env.testing and run "
                .'`createdb task_app_backend_testing`.'
            );
        }
    }
}
