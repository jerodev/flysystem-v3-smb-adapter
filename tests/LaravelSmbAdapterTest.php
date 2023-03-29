<?php

namespace Jerodev\Flysystem\Smb\Tests;

use Illuminate\Support\Facades\Storage;
use Jerodev\Flysystem\Smb\LaravelSmbAdapterProvider;
use Orchestra\Testbench\TestCase;

final class LaravelSmbAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new LaravelSmbAdapterProvider($this->app))->boot();
    }

    /** @test */
    public function it_should_write_files_using_laravel_adapter(): void
    {
        $config = \json_decode(\file_get_contents(__DIR__ . '/config.json'));

        $disk = Storage::build([
            'driver' => 'smb',
            'host' => $config->host,
            'path' => $config->share,
            'username' => $config->user,
            'password' => $config->password,
        ]);

        $disk->put('foo.bar', 'baz');

        $this->assertCount(1, $disk->files());
    }
}
