<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Factory;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Tourze\AwsS3StorageBundle\Adapter\PublicUrlGenerator;
use Tourze\FlysystemBundle\Factory\FilesystemFactory;
use Tourze\FlysystemBundle\Factory\FilesystemFactoryInterface;
use Tourze\FlysystemBundle\UrlGenerator\ControllerPublicUrlGenerator;

/**
 * FilesystemFactory 装饰器，用于支持 AWS S3
 *
 * 自动检测 S3 配置，如果配置完整则使用 S3，否则使用本地存储
 * 所有配置通过 $_ENV 在运行时读取，不在依赖注入容器中配置
 */
#[AsDecorator(decorates: FilesystemFactory::class)]
#[WithMonologChannel(channel: 'aws_s3_storage')]
final readonly class FilesystemFactoryDecorator implements FilesystemFactoryInterface
{
    public function __construct(
        private ControllerPublicUrlGenerator $publicUrlGenerator,
        #[AutowireDecorated] private FilesystemFactoryInterface $innerFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function createFilesystem(): FilesystemOperator
    {
        // 如果 S3 配置完整，使用 S3 存储
        if ($this->isS3Configured()) {
            $this->logger->debug('Creating AWS S3 file storage');

            return $this->createS3Filesystem();
        }

        // 否则使用原始的文件系统（本地存储）
        $this->logger->debug('AWS S3 is not config completed.', [
            'innerFactory' => $this->innerFactory::class,
        ]);

        return $this->innerFactory->createFilesystem();
    }

    /**
     * 检查 S3 配置是否完整
     *
     * 从 $_ENV 读取并验证必需的配置项
     */
    private function isS3Configured(): bool
    {
        return (($_ENV['AWS_S3_BUCKET'] ?? '') !== '')
            && (($_ENV['AWS_S3_REGION'] ?? '') !== '');
    }

    /**
     * 创建 S3 文件系统
     *
     * 所有配置从 $_ENV 运行时读取
     */
    private function createS3Filesystem(): FilesystemOperator
    {
        // 从 $_ENV 读取配置
        $region = is_string($_ENV['AWS_S3_REGION'] ?? null) ? $_ENV['AWS_S3_REGION'] : 'us-east-1';
        $bucket = is_string($_ENV['AWS_S3_BUCKET'] ?? null) ? $_ENV['AWS_S3_BUCKET'] : '';
        $prefix = is_string($_ENV['AWS_S3_PREFIX'] ?? null) ? $_ENV['AWS_S3_PREFIX'] : '';
        $accessKeyId = is_string($_ENV['AWS_S3_ACCESS_KEY_ID'] ?? null) ? $_ENV['AWS_S3_ACCESS_KEY_ID'] : null;
        $secretAccessKey = is_string($_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? null) ? $_ENV['AWS_S3_SECRET_ACCESS_KEY'] : null;
        $endpoint = is_string($_ENV['AWS_S3_ENDPOINT'] ?? null) ? $_ENV['AWS_S3_ENDPOINT'] : null;
        $cdnUrl = is_string($_ENV['AWS_S3_CDN_URL'] ?? null) ? $_ENV['AWS_S3_CDN_URL'] : null;

        $factory = new S3AdapterFactory();

        // 创建适配器
        $adapter = $factory->createFromConfig([
            'access_key_id' => $accessKeyId,
            'secret_access_key' => $secretAccessKey,
            'bucket' => $bucket,
            'region' => $region,
            'prefix' => $prefix,
            'endpoint' => $endpoint,
        ]);

        // 设置可见性转换器
        $visibility = new PortableVisibilityConverter();

        return new Filesystem(
            adapter: $adapter,
            config: [
                'visibility' => $visibility,
            ],
            publicUrlGenerator: $this->createUrlGenerator($cdnUrl, $endpoint, $bucket, $prefix)
        );
    }

    /**
     * 创建 URL 生成器
     *
     * 根据配置选择合适的 URL 生成器实现
     */
    private function createUrlGenerator(
        ?string $cdnUrl,
        ?string $endpoint,
        string $bucket,
        string $prefix,
    ): ControllerPublicUrlGenerator|PublicUrlGenerator {
        if (null !== $cdnUrl) {
            $this->logger->debug('Using CDN URL for public access', ['cdn_url' => $cdnUrl]);

            // 使用配置的 CDN 地址，不使用 S3 格式
            return new PublicUrlGenerator($cdnUrl, $bucket, $this->logger, $prefix, false);
        }

        if (null !== $endpoint) {
            $this->logger->debug('Using S3 endpoint for public access', ['endpoint' => $endpoint]);

            // 如果没有配置 CDN 但配置了 endpoint，使用 S3 格式
            return new PublicUrlGenerator($endpoint, $bucket, $this->logger, $prefix, true);
        }

        // 默认情况使用注入的公共 URL 生成器
        return $this->publicUrlGenerator;
    }
}
