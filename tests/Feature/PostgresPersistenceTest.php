<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresPersistenceTest extends TestCase
{
    public function test_ci_uses_postgresql_and_applies_the_application_schema(): void
    {
        if (! filter_var(env('REQUIRE_POSTGRES'), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('PostgreSQL verification runs in its dedicated CI job.');
        }

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('cache'));
    }
}
