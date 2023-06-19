<?php

namespace Jerodev\Flysystem\Smb\Tests;

use Icewind\SMB\IOptions;
use Illuminate\Filesystem\FilesystemAdapter;
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
    public function it_should_correctly_set_options(): void
    {
        $config = \json_decode(\file_get_contents(__DIR__ . '/config.json'), true);
        \assert(\is_array($config));

        $config = [
            'driver' => 'smb',
            'host' => $config['host'],
            'path' => $config['share'],
            'username' => $config['user'],
            'password' => $config['password'],
            'smb_version_min' => IOptions::PROTOCOL_SMB2,
            'smb_version_max' => IOptions::PROTOCOL_SMB2_24,
            'timeout' => 10,
        ];

        $this->app['config']->set('filesystems.disks.smb', $config);

        $disk = Storage::disk('smb');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertEquals($config, $disk->getConfig());
    }

    /** @test */
    public function it_should_write_files_using_laravel_adapter(): void
    {
        $config = \json_decode(\file_get_contents(__DIR__ . '/config.json'), true);
        \assert(\is_array($config));

        $disk = Storage::build([
            'driver' => 'smb',
            'host' => $config['host'],
            'path' => $config['share'],
            'username' => $config['user'],
            'password' => $config['password'],
        ]);

        $disk->put('foo.bar', 'baz');

        $this->assertCount(1, $disk->files());
    }
}
