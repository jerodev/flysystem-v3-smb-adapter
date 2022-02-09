<?php

namespace Jerodev\Flysystem\Smb;

use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\IShare;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Throwable;

class SmbAdapter implements FilesystemAdapter
{
    private FinfoMimeTypeDetector $mimeTypeDetector;
    private PathPrefixer $prefixer;

    public function __construct(
        private IShare $share,
        $prefix = null,
    ) {
        $this->mimeTypeDetector = new FinfoMimeTypeDetector();
        $this->prefixer = new PathPrefixer($prefix, DIRECTORY_SEPARATOR);
    }

    public function fileExists(string $path): bool
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $this->share->stat($location);
        } catch (NotFoundException) {
            return false;
        }

        return true;
    }

    public function directoryExists(string $path): bool
    {
        return $this->fileExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->recursiveCreateDir(dirname($path));

        $location = $this->prefixer->prefixPath($path);

        try {
            $stream = $this->share->write($location);
            fwrite($stream, $contents);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($location, '', $e);
        } finally {
             fclose($stream);
        }
    }

    public function writeStream(string $path, $resource, Config $config): void
    {
        $this->recursiveCreateDir(dirname($path));

        $location = $this->prefixer->prefixPath($path);

        try {
            $stream = $this->share->write($location);
            stream_copy_to_stream($resource, $stream);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($location, '', $e);
        } finally {
            fclose($stream);
        }
    }

    public function read(string $path): string
    {
        $stream = $this->readStream($path);

        $contents = stream_get_contents($stream);
        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        fclose($stream);

        return $contents;
    }

    public function readStream(string $path)
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $stream = $this->share->read($location);
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($location, '', $e);
        }

        return $stream;
    }

    public function delete(string $path): void
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $this->share->del($location);
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($location, '', $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $this->share->rmdir($location);
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($location, '', $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->recursiveCreateDir($path);
        } catch (Throwable $e) {
            throw UnableToCreateDirectory::dueToFailure($path, $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Sbm does not support visibility');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToSetVisibility::atLocation($path, 'Sbm does not support visibility');
    }

    public function mimeType(string $path): FileAttributes
    {
        $resource = $this->readStream($path);

        $mimeType = $this->mimeTypeDetector->detectMimeType($path, $resource);
        if ($mimeType === null) {
            throw UnableToRetrieveMetadata::mimeType($path, error_get_last()['message'] ?? '');
        }

        return new FileAttributes($path, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $fileInfo = $this->share->stat($location);
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($location, '', $e);
        }

        return new FileAttributes(
            $location,
            $fileInfo->getSize(),
            null,
            $fileInfo->getMTime(),

        );
    }

    public function fileSize(string $path): FileAttributes
    {
        // TODO: Implement fileSize() method.
    }

    public function listContents(string $path, bool $deep): iterable
    {
        // TODO: Implement listContents() method.
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // TODO: Implement move() method.
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // TODO: Implement copy() method.
    }

    protected function recursiveCreateDir($path)
    {
        if ($this->directoryExists($path)) {
            return;
        }

        $directories = explode(DIRECTORY_SEPARATOR, $path);
        if (count($directories) > 1) {
            $parentDirectories = array_splice($directories, 0, count($directories) - 1);
            $this->recursiveCreateDir(implode(DIRECTORY_SEPARATOR, $parentDirectories));
        }

        $location = $this->prefixer->prefixPath($path);

        $this->share->mkdir($location);
    }

    protected function getFileAttributes(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $fileInfo = $this->share->stat($location);
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($location, '', $e);
        }

        return new FileAttributes(
            $location,
            $fileInfo->getSize(),
            null,
            $fileInfo->getMTime(),

        );
    }
}