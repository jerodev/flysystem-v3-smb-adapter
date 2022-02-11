<?php

namespace Jerodev\Flysystem\Smb\Tests;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\IOptions;
use Icewind\SMB\Options;
use Icewind\SMB\ServerFactory;
use Jerodev\Flysystem\Smb\SmbAdapter;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\FilesystemAdapter;

class SmbAdapterTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $config = \json_decode(\file_get_contents(__DIR__ . '/config.json'));

        $options = new Options();
        $options->setMinProtocol(IOptions::PROTOCOL_SMB2);
        $options->setMaxProtocol(IOptions::PROTOCOL_SMB3);

        $server = (new ServerFactory())->createServer(
            $config->host,
            new BasicAuth(
                $config->user,
                'test',
                $config->password
            )
        );
        $share = $server->getShare($config->share);

        return new SmbAdapter($share, '');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::$adapter->clearDir('');
    }
}
