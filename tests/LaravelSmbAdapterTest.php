<?php

namespace Jerodev\Flysystem\Smb\Tests;

use Icewind\SMB\IOptions;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Jerodev\Flysystem\Smb\LaravelSmbAdapterProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LaravelSmbAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new LaravelSmbAdapterProvider($this->app))->boot();
    }

    #[Test]
    public function it_should_correctly_set_options(): void
    {
        /**
         * @var array{
         *     host: string,
         *     user: string,
         *     password: string,
         *     share: string,
         *     root: string,
         * } $config
         */
        $config = \json_decode(\file_get_contents(__DIR__ . '/config.json'), true);

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

    #[Test]
    public function it_should_write_files_using_laravel_adapter(): void
    {
        /**
         * @var array{
         *     host: string,
         *     user: string,
         *     password: string,
         *     share: string,
         *     root: string,
         * } $config
         */
        $config = \json_decode(\file_get_contents(__DIR__ . '/config.json'), true);

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
