# SMB adapter for Flysystem v3
[![run-tests](https://github.com/jerodev/flysystem-v3-smb-adapter/actions/workflows/run-tests.yml/badge.svg)](https://github.com/jerodev/flysystem-v3-smb-adapter/actions/workflows/run-tests.yml) [![Latest Stable Version](http://poser.pugx.org/jerodev/flysystem-v3-smb-adapter/v)](https://packagist.org/packages/jerodev/flysystem-v3-smb-adapter)

This package enables you to communicate with SMB shares through [Flysystem v3](https://github.com/thephpleague/flysystem).

## Installation
    
    composer require jerodev/flysystem-v3-smb-adapter

## Usage

The adapter uses the [Icewind SMB](https://github.com/icewind1991/SMB) package to communicate with the share.  
To use the flysystem adapter, you have to pass it an instance of [`\Icewind\SMB\IShare`](https://github.com/icewind1991/SMB/blob/master/src/IShare.php). Below is an example of how to create a share instance using the factory provided by Icewind SMB. 

```php
$server = (new \Icewind\SMB\ServerFactory())->createServer(
    $config->host,
    new \Icewind\SMB\BasicAuth(
        $config->user,
        'test',
        $config->password
    )
);
$share = $server->getShare($config->share);

return new \Jerodev\Flysystem\Smb\SmbAdapter($share, '');
```

## Laravel Filesystem
The package also ships with a Laravel service provider that automatically registers a driver for you. Laravel will discover this provider for you when installing this package.
All you have to do is configure the share in your `config/filesystems.php` similar to the example below.

```php
'disks' => [
    'smb_share' => [
        'driver' => 'smb',
        'workgroup' => 'WORKGROUP',
        'host' => \env('SMB_HOST', '127.0.0.1'),
        'path' => \env('SMB_PATH', 'test'),
        'username' => \env('SMB_USERNAME', ''),
        'password' => \env('SMB_PASSWORD', ''),
        
        // Optional Icewind SMB options
        'smb_version_min' => \Icewind\SMB\IOptions::PROTOCOL_SMB2,
        'smb_version_max' => \Icewind\SMB\IOptions::PROTOCOL_SMB2_24,
        'timeout' => 20,
    ],
],
```
