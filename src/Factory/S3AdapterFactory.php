<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Factory;

use AsyncAws\Core\Configuration;
use AsyncAws\S3\S3Client as AsyncS3Client;
use Tourze\AwsS3StorageBundle\Adapter\AwsS3Adapter;
use Tourze\AwsS3StorageBundle\Client\S3Client;

/**
 * AWS S3适配器工厂类
 */
final readonly class S3AdapterFactory
{
    /**
     * 创建S3适配器
     *
     * @param string      $accessKeyId     访问密钥ID
     * @param string      $secretAccessKey 密钥
     * @param string      $bucket          桶名称
     * @param string      $region          区域
     * @param string      $prefix          路径前缀
     * @param string|null $endpoint        自定义端点
     */
    public function create(
        string $accessKeyId,
        string $secretAccessKey,
        string $bucket,
        string $region,
        string $prefix = '',
        ?string $endpoint = null,
    ): AwsS3Adapter {
        // 构建基础配置
        $clientConfigArray = [
            'region' => $region,
            'accessKeyId' => $accessKeyId,
            'accessKeySecret' => $secretAccessKey,
        ];

        // 如果提供了自定义端点（如 MinIO），设置端点
        if (null !== $endpoint) {
            $clientConfigArray['endpoint'] = $endpoint;
            $clientConfigArray['pathStyleEndpoint'] = '1';
        }

        $clientConfig = Configuration::create($clientConfigArray);
        $asyncClient = new AsyncS3Client($clientConfig);
        $client = new S3Client($asyncClient);

        return new AwsS3Adapter($client, $bucket, $prefix);
    }

    /**
     * 从配置数组创建S3适配器
     *
     * @param array $config 配置数组，必须包含：
     *                      - access_key_id: 访问密钥ID
     *                      - secret_access_key: 密钥
     *                      - bucket: 桶名称
     *                      - region: 区域
     *                      可选参数：
     *                      - prefix: 路径前缀
     *                      - endpoint: 自定义端点
     */
    /**
     * @param array<string, mixed> $config
     */
    public function createFromConfig(array $config): AwsS3Adapter
    {
        $accessKeyId = $config['access_key_id'] ?? null;
        $secretAccessKey = $config['secret_access_key'] ?? null;
        $bucket = $config['bucket'] ?? null;
        $region = $config['region'] ?? null;
        $prefix = $config['prefix'] ?? '';
        $endpoint = $config['endpoint'] ?? null;

        if (!is_string($accessKeyId) || !is_string($secretAccessKey) || !is_string($bucket) || !is_string($region)) {
            throw new \InvalidArgumentException('Missing or invalid required configuration parameters');
        }

        if (!is_string($prefix)) {
            $prefix = '';
        }

        if (null !== $endpoint && !is_string($endpoint)) {
            $endpoint = null;
        }

        return $this->create(
            $accessKeyId,
            $secretAccessKey,
            $bucket,
            $region,
            $prefix,
            $endpoint
        );
    }
}
