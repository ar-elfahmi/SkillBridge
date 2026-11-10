<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    public function test_database_connection_uses_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame(1, (int) DB::selectOne('select 1 as ok')->ok);
    }

    public function test_postgresql_supports_jsonb(): void
    {
        $row = DB::selectOne("select ('[0.1, 0.2]'::jsonb ->> 1) as v");

        $this->assertSame('0.2', $row->v);
    }

    public function test_redis_connection_works(): void
    {
        $this->assertTrue((bool) Redis::connection()->ping());
    }

    public function test_private_disk_is_not_publicly_served(): void
    {
        $config = config('filesystems.disks.private');

        $this->assertSame('local', $config['driver']);
        $this->assertFalse($config['serve']);
        $this->assertStringNotContainsString('public', Storage::disk('private')->path(''));
    }

    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
