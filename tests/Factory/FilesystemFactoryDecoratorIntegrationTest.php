<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Factory;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AwsS3StorageBundle\Adapter\AwsS3Adapter;
use Tourze\AwsS3StorageBundle\Adapter\PublicUrlGenerator;
use Tourze\AwsS3StorageBundle\Factory\FilesystemFactoryDecorator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * FilesystemFactoryDecorator 集成测试
 *
 * 测试 AWS S3 文件系统工厂装饰器的集成功能
 * 包括 PublicUrlGenerator 的间接测试
 *
 * @internal
 */
#[CoversClass(FilesystemFactoryDecorator::class)]
#[RunTestsInSeparateProcesses]
class FilesystemFactoryDecoratorIntegrationTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试不需要特殊设置
    }

    public function testCreateFilesystemWithoutS3ConfigShouldUseInnerFactory(): void
    {
        // Arrange - 确保没有 S3 配置
        $originalEnv = $_ENV;
        unset($_ENV['AWS_S3_BUCKET'], $_ENV['AWS_S3_REGION']);

        try {
            // Act
            $factory = self::getService(FilesystemFactoryDecorator::class);
            $filesystem = $factory->createFilesystem();

            // Assert - 应该返回本地文件系统
            $this->assertInstanceOf(Filesystem::class, $filesystem);

            // 验证这是本地文件系统而不是 S3 文件系统
            // 通过反射检查适配器类型
            $reflection = new \ReflectionClass($filesystem);
            $adapterProperty = $reflection->getProperty('adapter');
            $adapterProperty->setAccessible(true);
            $adapter = $adapterProperty->getValue($filesystem);

            // 本地文件系统使用 LocalFilesystemAdapter
            $this->assertInstanceOf(LocalFilesystemAdapter::class, $adapter);
        } finally {
            $_ENV = $originalEnv;
        }
    }

    public function testCreateFilesystemWithS3ConfigShouldCreateS3Filesystem(): void
    {
        // Arrange - 设置 S3 配置
        $originalEnv = $_ENV;
        $_ENV['AWS_S3_BUCKET'] = 'test-bucket';
        $_ENV['AWS_S3_REGION'] = 'us-east-1';
        $_ENV['AWS_S3_PREFIX'] = 'uploads';
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'test-access-key';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'test-secret-key';

        try {
            // Act
            $factory = self::getService(FilesystemFactoryDecorator::class);
            $filesystem = $factory->createFilesystem();

            // Assert
            $this->assertInstanceOf(Filesystem::class, $filesystem);

            // 验证这是 S3 文件系统
            $reflection = new \ReflectionClass($filesystem);
            $adapterProperty = $reflection->getProperty('adapter');
            $adapterProperty->setAccessible(true);
            $adapter = $adapterProperty->getValue($filesystem);

            // S3 文件系统使用 AwsS3Adapter
            $this->assertInstanceOf(AwsS3Adapter::class, $adapter);

            // 验证 PublicUrlGenerator 已创建
            $urlGeneratorProperty = $reflection->getProperty('publicUrlGenerator');
            $urlGeneratorProperty->setAccessible(true);
            $urlGenerator = $urlGeneratorProperty->getValue($filesystem);

            $this->assertInstanceOf(PublicUrlGenerator::class, $urlGenerator);
        } finally {
            $_ENV = $originalEnv;
        }
    }

    public function testCreateFilesystemWithCdnUrlShouldCreatePublicUrlGeneratorWithCdnSettings(): void
    {
        // Arrange - 设置 CDN 配置
        $originalEnv = $_ENV;
        $_ENV['AWS_S3_BUCKET'] = 'test-bucket';
        $_ENV['AWS_S3_REGION'] = 'us-east-1';
        $_ENV['AWS_S3_PREFIX'] = 'static';
        $_ENV['AWS_S3_CDN_URL'] = 'https://d123456789abcdef.cloudfront.net';
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'test-access-key';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'test-secret-key';

        try {
            // Act
            $factory = self::getService(FilesystemFactoryDecorator::class);
            $filesystem = $factory->createFilesystem();

            // Assert
            $this->assertInstanceOf(Filesystem::class, $filesystem);

            // 验证 PublicUrlGenerator 配置
            $reflection = new \ReflectionClass($filesystem);
            $urlGeneratorProperty = $reflection->getProperty('publicUrlGenerator');
            $urlGeneratorProperty->setAccessible(true);
            $urlGenerator = $urlGeneratorProperty->getValue($filesystem);

            $this->assertInstanceOf(PublicUrlGenerator::class, $urlGenerator);

            // 测试 URL 生成是否使用 CDN
            $config = new Config();
            $url = $urlGenerator->publicUrl('test.jpg', $config);
            $this->assertStringStartsWith('https://d123456789abcdef.cloudfront.net/', $url);
            $this->assertStringContainsString('static/test.jpg', $url);
        } finally {
            $_ENV = $originalEnv;
        }
    }

    public function testIsS3ConfiguredShouldReturnFalseWhenMissingRequiredConfig(): void
    {
        // Arrange - 移除必需的配置
        $originalEnv = $_ENV;
        unset($_ENV['AWS_S3_BUCKET']);
        $_ENV['AWS_S3_REGION'] = 'us-east-1';

        try {
            // Act
            $factory = self::getService(FilesystemFactoryDecorator::class);
            $filesystem = $factory->createFilesystem();

            // Assert - 应该回退到本地文件系统
            $reflection = new \ReflectionClass($filesystem);
            $adapterProperty = $reflection->getProperty('adapter');
            $adapterProperty->setAccessible(true);
            $adapter = $adapterProperty->getValue($filesystem);

            $this->assertInstanceOf(LocalFilesystemAdapter::class, $adapter);
        } finally {
            $_ENV = $originalEnv;
        }
    }
}
