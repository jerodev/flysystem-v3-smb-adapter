<?php

namespace Jerodev\Flysystem\Smb\Tests;

use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\IShare;
use Jerodev\Flysystem\Smb\SmbAdapter;
use League\Flysystem\Config;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SmbAdapterTest extends TestCase
{
    private SmbAdapter $adapter;
    private IShare&MockInterface $share;

    protected function setUp(): void
    {
        parent::setUp();

        $this->share = \Mockery::mock(IShare::class);
        $this->adapter = new SmbAdapter($this->share, '');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    #[Test]
    public function it_should_create_directories_and_subdirectories(): void
    {
        $this->share->shouldReceive('stat')->andThrow(new NotFoundException());

        $this->share->shouldReceive('mkdir')->with('0')->once();
        $this->share->shouldReceive('mkdir')->with('0/foo')->once();
        $this->share->shouldReceive('mkdir')->with('0/foo/bar')->once();
        $this->share->shouldReceive('mkdir')->with('0/foo/bar/baz')->once();

        $this->adapter->createDirectory('0/foo/bar/baz', new Config());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_should_create_file_directory(): void
    {
        $this->share->shouldReceive('stat')->andThrow(new NotFoundException());
        $this->share->shouldReceive('write')->with('0/foo/bar/baz.txt')->once()->andReturn(\fopen('php://temp', 'r+'));

        $this->share->shouldReceive('mkdir')->with('0')->once();
        $this->share->shouldReceive('mkdir')->with('0/foo')->once();
        $this->share->shouldReceive('mkdir')->with('0/foo/bar')->once();

        $this->adapter->write('0/foo/bar/baz.txt', 'contents', new Config());

        $this->expectNotToPerformAssertions();
    }
}