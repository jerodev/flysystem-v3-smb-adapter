<?php

namespace Jerodev\Flysystem\Smb;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\ServerFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class LaravelSmbAdapterProvider extends ServiceProvider
{
    public function register()
    {
        Storage::extend('smb', static function ($app, $config) {
            $server = (new ServerFactory())->createServer($config['host'], new BasicAuth($config['username'], $config['workgroup'], $config['password']));
            $share = $server->getShare($config['path']);

            return new Filesystem(new SmbAdapter($share), $config);
        });
    }
}
