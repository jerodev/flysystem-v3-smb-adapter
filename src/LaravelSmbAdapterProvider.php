<?php

namespace Jerodev\Flysystem\Smb;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\Options;
use Icewind\SMB\ServerFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class LaravelSmbAdapterProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('smb', static function ($app, $config) {
            $options = new Options();
            $options->setMinProtocol($config['smb_version_min'] ?? null);
            $options->setMinProtocol($config['smb_version_max'] ?? null);
            $options->setTimeout($config['timeout'] ?? 20);

            $server = (new ServerFactory($options))->createServer(
                $config['host'],
                new BasicAuth($config['username'], $config['workgroup'] ?? 'WORKGROUP', $config['password'])
            );
            $share = $server->getShare($config['path']);
            $adapter = new SmbAdapter($share);

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }
}
