<?php

declare(strict_types=1);

namespace App\Services\Storage;

use finfo;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Throwable;

final class WriteWrappedAdapter implements FilesystemAdapter
{
    public function __construct(
        private readonly FilesystemAdapter $delegate,
        private readonly KeyEnvelope $envelope,
    ) {}

    public function fileExists(string $path): bool
    {
        return $this->delegate->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->delegate->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->delegate->write($path, $this->envelope->encrypt($contents), $config);
        } catch (UnableToWriteFile $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, mixed $contents, Config $config): void
    {
        if (! is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'Expected a readable stream.');
        }

        $plaintext = stream_get_contents($contents);
        if ($plaintext === false) {
            throw UnableToWriteFile::atLocation($path, 'Unable to read source stream.');
        }

        $this->write($path, $plaintext, $config);
    }

    public function read(string $path): string
    {
        try {
            return $this->envelope->decrypt($this->delegate->read($path));
        } catch (UnableToReadFile $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        if (! is_resource($stream)) {
            throw UnableToReadFile::fromLocation($path, 'Unable to open temporary memory stream.');
        }

        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->delegate->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->delegate->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->delegate->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->delegate->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->delegate->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $plaintext = $this->read($path);
        $detector = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $detector->buffer($plaintext) ?: 'application/octet-stream';

        return new FileAttributes($path, mimeType: $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->delegate->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            return new FileAttributes($path, fileSize: strlen($this->read($path)));
        } catch (UnableToReadFile $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    /**
     * @return iterable<StorageAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        return $this->delegate->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->delegate->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->delegate->copy($source, $destination, $config);
    }
}
