<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Client;

/**
 * S3 客户端接口
 *
 * 定义 AWS S3 API 客户端的核心方法
 */
interface S3ClientInterface
{
    /**
     * 获取对象元数据
     * @return array<string, mixed>
     */
    public function headObject(string $bucket, string $key): array;

    /**
     * 上传对象
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function putObject(string $bucket, string $key, string $body, array $options = []): array;

    /**
     * 下载对象
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function getObject(string $bucket, string $key, array $options = []): array;

    /**
     * 删除对象
     * @return array<string, mixed>
     */
    public function deleteObject(string $bucket, string $key): array;

    /**
     * 批量删除对象
     * @param array<array<string, mixed>> $objects
     * @return array<string, mixed>
     */
    public function deleteObjects(string $bucket, array $objects): array;

    /**
     * 列举对象
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function listObjects(string $bucket, array $options = []): array;

    /**
     * 复制对象
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function copyObject(string $sourceBucket, string $sourceKey, string $destBucket, string $destKey, array $options = []): array;
}
