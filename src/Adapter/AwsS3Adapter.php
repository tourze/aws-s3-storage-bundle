<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Adapter;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Tourze\AwsS3StorageBundle\Client\S3ClientInterface;
use Tourze\AwsS3StorageBundle\Exception\ConfigurationException;
use Tourze\AwsS3StorageBundle\Exception\S3Exception;
use Tourze\AwsS3StorageBundle\Helper\S3OperationHelper;

/**
 * AWS S3适配器，实现Flysystem接口
 *
 * 该适配器实现了League\Flysystem\FilesystemAdapter接口，
 * 提供了AWS S3的文件系统操作功能。
 *
 * 主要功能：
 * - 文件操作：读取、写入、删除、复制、移动
 * - 目录操作：创建、删除、列举（虚拟目录）
 * - 元数据操作：获取文件大小、MIME类型、最后修改时间
 * - 流操作：支持大文件的流式读写
 *
 * 注意事项：
 * - S3中的目录是虚拟的，通过对象键的前缀来模拟
 * - 不支持通过ACL修改文件可见性（visibility）
 * - 使用PathPrefixer来处理路径前缀
 */
class AwsS3Adapter implements FilesystemAdapter
{
    private const MAX_KEYS_FOR_DIRECTORY_CHECK = 1;

    private PathPrefixer $prefixer;

    private readonly S3OperationHelper $operationHelper;

    public function __construct(
        private readonly S3ClientInterface $client,
        private readonly string $bucket,
        string $prefix = '',
    ) {
        if ('' === $this->bucket) {
            throw new ConfigurationException('Bucket name cannot be empty');
        }

        $this->prefixer = new PathPrefixer($prefix);
        $this->operationHelper = new S3OperationHelper($this->client, $this->prefixer);
    }

    public function fileExists(string $path): bool
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $this->client->headObject($this->bucket, $location);

            return true;
        } catch (S3Exception $e) {
            // S3特定的异常，通常是文件不存在，返回false
            return false;
        } catch (\Throwable $e) {
            // 记录未知异常但不暴露给调用者，保持接口一致性
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        $location = $this->prefixer->prefixDirectoryPath($path);

        // S3中目录是虚拟的，我们通过列出以该路径为前缀的对象来判断
        try {
            $result = $this->client->listObjects($this->bucket, [
                'prefix' => $location,
                'max-keys' => self::MAX_KEYS_FOR_DIRECTORY_CHECK,
            ]);

            return [] !== $result['Contents'];
        } catch (S3Exception $e) {
            // S3特定的异常，通常是目录不存在，返回false
            return false;
        } catch (\Throwable $e) {
            // 记录未知异常但不暴露给调用者，保持接口一致性
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $options = $this->operationHelper->extractOptionsFromConfig($config);
            $this->client->putObject($this->bucket, $location, $contents, $options);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $options = $this->operationHelper->extractOptionsFromConfig($config);

            // 检查流是否有效
            if (!is_resource($contents)) {
                throw new S3Exception('Invalid stream resource');
            }

            $body = stream_get_contents($contents);

            if (false === $body) {
                throw new S3Exception('Unable to read stream contents');
            }

            $this->client->putObject($this->bucket, $location, $body, $options);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $result = $this->client->getObject($this->bucket, $location);

            if (!isset($result['Body']) || !is_string($result['Body'])) {
                throw new S3Exception('Invalid response body from S3');
            }

            return $result['Body'];
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $result = $this->client->getObject($this->bucket, $location);

            if (!isset($result['Body']) || !is_string($result['Body'])) {
                throw new S3Exception('Invalid response body from S3');
            }

            $stream = fopen('php://temp', 'r+');

            if (false === $stream) {
                throw new S3Exception('Unable to create temp stream');
            }

            fwrite($stream, $result['Body']);
            rewind($stream);

            return $stream;
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $this->client->deleteObject($this->bucket, $location);
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $location = $this->prefixer->prefixDirectoryPath($path);

        try {
            $this->operationHelper->deleteDirectoryObjects($this->bucket, $location);
        } catch (\Exception $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // S3中目录是虚拟的，创建一个以/结尾的空对象表示目录
        $location = $this->prefixer->prefixDirectoryPath($path);

        try {
            $this->client->putObject($this->bucket, $location, '');
        } catch (\Exception $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // AWS S3不支持通过单个对象ACL修改可见性
        throw UnableToSetVisibility::atLocation($path, 'AWS S3 does not support visibility changes through ACL.');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes(
            $path,
            null,
            Visibility::PRIVATE
        );
    }

    public function mimeType(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $result = $this->client->headObject($this->bucket, $location);
            $mimeType = $result['ContentType'] ?? null;
            if (null !== $mimeType && !is_string($mimeType)) {
                $mimeType = null;
            }

            return new FileAttributes(
                $path,
                null,
                null,
                null,
                $mimeType
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $result = $this->client->headObject($this->bucket, $location);
            $lastModified = null;
            if (isset($result['LastModified']) && is_string($result['LastModified'])) {
                $timestamp = strtotime($result['LastModified']);
                $lastModified = false !== $timestamp ? $timestamp : null;
            }

            return new FileAttributes(
                $path,
                null,
                null,
                $lastModified
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            $result = $this->client->headObject($this->bucket, $location);
            $fileSize = null;
            if (isset($result['ContentLength']) && (is_int($result['ContentLength']) || is_string($result['ContentLength']))) {
                $fileSize = (int) $result['ContentLength'];
            }

            return new FileAttributes(
                $path,
                $fileSize
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $location = $this->prefixer->prefixDirectoryPath($path);
        $continuationToken = null;

        do {
            try {
                $options = $this->buildListObjectsOptions($location, $continuationToken, $deep);
                $result = $this->client->listObjects($this->bucket, $options);

                // yield文件
                foreach ($this->operationHelper->processObjectContents($result) as $item) {
                    yield $item;
                }

                // yield目录
                foreach ($this->operationHelper->processDirectoryPrefixes($result, $deep) as $item) {
                    yield $item;
                }

                $nextToken = $result['NextContinuationToken'] ?? null;
                $continuationToken = is_string($nextToken) ? $nextToken : null;
            } catch (\Exception $e) {
                throw new S3Exception('Unable to list contents: ' . $e->getMessage(), 0, $e);
            }
        } while (null !== $continuationToken);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListObjectsOptions(string $location, ?string $continuationToken, bool $deep): array
    {
        $options = [
            'prefix' => $location,
        ];

        if (null !== $continuationToken) {
            $options['continuation-token'] = $continuationToken;
        }

        if (!$deep) {
            // 使用delimiter参数实现非递归列举
            $options['delimiter'] = '/';
        }

        return $options;
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $sourceLocation = $this->prefixer->prefixPath($source);
        $destinationLocation = $this->prefixer->prefixPath($destination);

        try {
            $options = $this->operationHelper->extractOptionsFromConfig($config);
            // 使用S3的复制对象接口
            $this->client->copyObject(
                $this->bucket,
                $sourceLocation,
                $this->bucket,
                $destinationLocation,
                $options
            );
        } catch (\Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }
}
