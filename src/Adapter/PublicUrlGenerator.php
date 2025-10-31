<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Adapter;

use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\PublicUrlGenerator as FlysystemPublicUrlGenerator;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\AwsS3StorageBundle\Exception\S3Exception;

/**
 * AWS S3公共URL生成器
 *
 * 用于生成S3对象的公共访问URL
 * 支持两种模式：
 * 1. S3 原生格式：https://{bucket}.s3.{region}.amazonaws.com/{path}
 * 2. CloudFront/自定义域名格式：https://{domain}/{path}
 */
#[WithMonologChannel(channel: 'aws_s3_storage')]
class PublicUrlGenerator implements FlysystemPublicUrlGenerator
{
    private string $baseUrl;

    private string $bucket;

    /**
     * @param string $baseUrl      基础URL，可以是：
     *                             - S3 endpoint (如 s3.us-east-1.amazonaws.com)
     *                             - CloudFront 域名 (如 d123456789.cloudfront.net)
     *                             - 自定义域名 (如 files.example.com)
     * @param string $bucket       桶名称
     * @param string $prefix       路径前缀
     * @param bool   $useS3Format  是否使用S3格式（bucket作为子域名）
     */
    public function __construct(
        string $baseUrl,
        string $bucket,
        private readonly LoggerInterface $logger,
        private readonly string $prefix = '',
        bool $useS3Format = true,
    ) {
        $this->bucket = $bucket;
        $this->logger->debug('Initializing PublicUrlGenerator', [
            'base_url' => $baseUrl,
            'bucket' => $bucket,
            'prefix' => $prefix,
            'use_s3_format' => $useS3Format,
        ]);

        // 移除协议前缀
        $cleanUrl = preg_replace('#^https?://#', '', $baseUrl);

        if ($useS3Format) {
            // S3 格式：bucket 作为子域名
            $this->baseUrl = sprintf('https://%s.%s', $bucket, $cleanUrl);
            $this->logger->debug('Using S3 format URL', ['url' => $this->baseUrl]);
        } else {
            // CloudFront 格式：直接使用域名
            $this->baseUrl = sprintf('https://%s', $cleanUrl);
            $this->logger->debug('Using CloudFront format URL', ['url' => $this->baseUrl]);
        }
    }

    public function publicUrl(string $path, Config $config): string
    {
        // 输入验证：检查路径有效性
        $this->validatePath($path);

        $this->logger->debug('Generating public URL', [
            'path' => $path,
            'bucket' => $this->bucket,
            'prefix' => $this->prefix,
        ]);

        // 构建完整的对象路径
        $objectPath = '' !== $this->prefix ? $this->prefix . '/' . ltrim($path, '/') : $path;

        // 对路径进行URL编码，但保留斜杠
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $objectPath)));

        $publicUrl = sprintf('%s/%s', $this->baseUrl, $encodedPath);

        // 日志记录时避免记录可能包含敏感信息的完整URL
        $this->logger->info('Public URL generated', [
            'original_path' => $path,
            'object_path' => $objectPath,
            'url_prefix' => $this->baseUrl,
        ]);

        return $publicUrl;
    }

    /**
     * 验证路径安全性，防止路径遍历攻击
     *
     * @param string $path 待验证的路径
     * @throws S3Exception 当路径包含不安全字符时
     * @since 1.0.0
     */
    private function validatePath(string $path): void
    {
        // 检查空路径
        if ('' === $path) {
            throw new S3Exception('Path cannot be empty');
        }

        // 检查控制字符
        if (preg_match('/[\x00-\x1F\x7F]/', $path) > 0) {
            throw new S3Exception('Path contains invalid control characters');
        }

        // 检查路径遍历攻击
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '//')) {
            throw new S3Exception('Path contains potentially dangerous traversal patterns');
        }

        // 检查路径长度限制
        if (strlen($path) > 1024) {
            throw new S3Exception('Path exceeds maximum length limit');
        }
    }
}
