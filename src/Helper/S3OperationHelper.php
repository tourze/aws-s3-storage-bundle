<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Helper;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\PathPrefixer;
use Tourze\AwsS3StorageBundle\Client\S3ClientInterface;

/**
 * S3操作帮助类，处理S3相关的复杂逻辑
 */
final class S3OperationHelper
{
    public function __construct(
        private readonly S3ClientInterface $client,
        private readonly PathPrefixer $prefixer,
    ) {
    }

    /**
     * 批量删除目录下的所有对象
     */
    public function deleteDirectoryObjects(string $bucket, string $location): void
    {
        $objects = $this->listAllObjects($bucket, $location);

        if ([] !== $objects) {
            $this->client->deleteObjects($bucket, $objects);
        }
    }

    /**
     * 列出指定路径下的所有对象
     *
     * @return array<int, array{Key: string}>
     */
    public function listAllObjects(string $bucket, string $location): array
    {
        $objects = [];
        $continuationToken = null;

        do {
            $options = ['prefix' => $location];

            if (null !== $continuationToken) {
                $options['continuation-token'] = $continuationToken;
            }

            $result = $this->client->listObjects($bucket, $options);
            // 使用数组展开操作符替代array_merge，提高性能
            $objectKeys = $this->extractObjectKeys($result);
            array_push($objects, ...$objectKeys);

            $nextToken = $result['NextContinuationToken'] ?? null;
            $continuationToken = is_string($nextToken) ? $nextToken : null;
        } while (null !== $continuationToken);

        return $objects;
    }

    /**
     * 从结果中提取对象键
     *
     * @param array<string, mixed> $result
     * @return array<int, array{Key: string}>
     */
    private function extractObjectKeys(array $result): array
    {
        $contents = $result['Contents'] ?? [];
        if (!is_array($contents)) {
            return [];
        }

        $keys = [];
        foreach ($contents as $object) {
            if (!is_array($object)) {
                continue;
            }
            // 检查是否是string-keyed array并且有Key字段
            if (!$this->isStringKeyedArray($object) || !isset($object['Key']) || !is_string($object['Key'])) {
                continue;
            }
            /** @var array<string, mixed> $object */
            /** @var string $key */
            $key = $object['Key'];
            $keys[] = ['Key' => $key];
        }

        return $keys;
    }

    /**
     * 处理对象内容，生成FileAttributes
     *
     * @param array<string, mixed> $result
     * @return iterable<FileAttributes>
     */
    public function processObjectContents(array $result): iterable
    {
        $contents = $result['Contents'] ?? [];
        if (!is_array($contents) || [] === $contents) {
            return;
        }

        foreach ($contents as $object) {
            if (!is_array($object)) {
                continue;
            }
            // 检查是否是string-keyed array并且有Key字段
            if (!$this->isStringKeyedArray($object) || !isset($object['Key']) || !is_string($object['Key'])) {
                continue;
            }
            /** @var array<string, mixed> $object */
            /** @var string $key */
            $key = $object['Key'];

            $objectPath = $this->prefixer->stripPrefix($key);

            // 跳过目录标记对象（以'/' 结尾的对象）
            if (str_ends_with($key, '/')) {
                continue;
            }

            yield new FileAttributes(
                $objectPath,
                $this->extractSize($object),
                null,
                $this->extractLastModified($object)
            );
        }
    }

    /**
     * 处理目录前缀，生成DirectoryAttributes
     *
     * @param array<string, mixed> $result
     * @return iterable<DirectoryAttributes>
     */
    public function processDirectoryPrefixes(array $result, bool $deep): iterable
    {
        if ($deep) {
            return;
        }

        $commonPrefixes = $result['CommonPrefixes'] ?? [];
        if (!is_array($commonPrefixes) || [] === $commonPrefixes) {
            return;
        }

        foreach ($commonPrefixes as $prefix) {
            if (is_array($prefix) && isset($prefix['Prefix']) && is_string($prefix['Prefix'])) {
                $directoryPath = $this->prefixer->stripPrefix($prefix['Prefix']);
                yield new DirectoryAttributes(rtrim($directoryPath, '/'));
            }
        }
    }

    /**
     * 从Config中提取S3选项
     *
     * @return array<string, mixed>
     */
    public function extractOptionsFromConfig(Config $config): array
    {
        $options = [];

        $contentType = $config->get('ContentType');
        if (null !== $contentType) {
            $options['ContentType'] = $contentType;
        }

        $metadata = $config->get('metadata');
        if (is_array($metadata)) {
            $options['Metadata'] = $metadata;
        }

        return $options;
    }

    /**
     * 检查数组是否是string-keyed
     * @param array<mixed, mixed> $array
     * @return bool
     */
    private function isStringKeyedArray(array $array): bool
    {
        if ([] === $array) {
            return true;
        }

        foreach (array_keys($array) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 提取对象大小
     * @param array<string, mixed> $object
     */
    private function extractSize(array $object): ?int
    {
        if (isset($object['Size']) && (is_int($object['Size']) || is_string($object['Size']))) {
            return (int) $object['Size'];
        }

        return null;
    }

    /**
     * 提取最后修改时间
     * @param array<string, mixed> $object
     */
    private function extractLastModified(array $object): ?int
    {
        if (isset($object['LastModified']) && is_string($object['LastModified'])) {
            $timestamp = strtotime($object['LastModified']);

            return false !== $timestamp ? $timestamp : null;
        }

        return null;
    }
}
