<?php

namespace Jerodev\Flysystem\Smb;

use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\IShare;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
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

    private array $fakeVisibility = [];

    public function __construct(
        private IShare $share,
        string $prefix = '',
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
        $this->recursiveCreateDir(\dirname($path));

        $location = $this->prefixer->prefixPath($path);

        try {
            $stream = $this->share->write($location);
            \fwrite($stream, $contents);

            if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
                $this->fakeVisibility[$path] = $visibility;
            }
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($location, '', $e);
        } finally {
            if (isset($stream)) {
                \fclose($stream);
            }
        }
    }

    public function writeStream(string $path, $resource, Config $config): void
    {
        $this->recursiveCreateDir(\dirname($path));

        $location = $this->prefixer->prefixPath($path);

        try {
            $stream = $this->share->write($location);
            \stream_copy_to_stream($resource, $stream);

            if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
                $this->fakeVisibility[$path] = $visibility;
            }
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($location, '', $e);
        } finally {
            if (isset($stream)) {
                \fclose($stream);
            }
        }
    }

    public function read(string $path): string
    {
        try {
            $stream = $this->readStream($path);
            $contents = \stream_get_contents($stream);
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        } finally {
            if (isset($stream)) {
                \fclose($stream);
            }
        }

        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path);
        }

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
        } catch (NotFoundException) {
            // We should ignore exceptions if the file did not exist in the first place.
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($location, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $this->share->rmdir($location);
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($location, $e->getMessage(), $e);
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
        if (! $this->fileExists($path)) {
            throw UnableToSetVisibility::atLocation($path, 'File does not exist');
        }

        $this->fakeVisibility[$path] = $visibility;
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $resource = $this->readStream($path);
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }

        $mimeType = $this->mimeTypeDetector->detectMimeType($path, $resource);
        \fclose($resource);

        if ($mimeType === null) {
            throw UnableToRetrieveMetadata::mimeType($path, \error_get_last()['message'] ?? '');
        }

        return new FileAttributes($path, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        foreach ($this->share->dir($path) as $fileInfo) {
            if ($fileInfo->isDirectory()) {
                yield new DirectoryAttributes($fileInfo->getPath(), $this->fakeVisibility[$fileInfo->getPath()] ?? null, $fileInfo->getMTime());
                if ($deep) {
                    foreach ($this->listContents($fileInfo->getPath(), true) as $deepFileInfo) {
                        yield $deepFileInfo;
                    }
                }
            } else {
                yield new FileAttributes($fileInfo->getPath(), $fileInfo->getSize(), $this->fakeVisibility[$fileInfo->getPath()] ?? null, $fileInfo->getMTime());
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->share->rename($source, $destination);
        } catch (Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $content = $this->read($source);
        $this->write($destination, $content, $config);

        $this->fakeVisibility[$destination] = $config->get(Config::OPTION_VISIBILITY, $this->fakeVisibility[$source] ?? null);
    }

    /** Recursively remove all data from a folder */
    public function clearDir(string $path): void
    {
        foreach ($this->listContents($path, false) as $content) {
            if ($content instanceof DirectoryAttributes) {
                $this->clearDir($content->path());
                $this->deleteDirectory($content->path());
            } else {
                $this->delete($content->path());
            }
        }
    }

    protected function recursiveCreateDir($path)
    {
        if ($this->directoryExists($path)) {
            return;
        }

        $directories = \explode(DIRECTORY_SEPARATOR, $path);
        if (\count($directories) > 1) {
            $parentDirectories = \array_splice($directories, 0, \count($directories) - 1);
            $this->recursiveCreateDir(\implode(DIRECTORY_SEPARATOR, $parentDirectories));
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

        if ($fileInfo->isDirectory()) {
            throw UnableToRetrieveMetadata::lastModified($location, "'{$path}' is a directory");
        }

        return new FileAttributes(
            $location,
            $fileInfo->getSize(),
            $this->fakeVisibility[$path] ?? null,
            $fileInfo->getMTime(),
        );
    }
}
