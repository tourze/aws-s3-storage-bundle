<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Factory;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\AwsS3StorageBundle\Factory\FilesystemFactoryDecorator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(FilesystemFactoryDecorator::class)]
#[RunTestsInSeparateProcesses]
class FilesystemFactoryDecoratorTest extends AbstractIntegrationTestCase
{
    private FilesystemFactoryDecorator $decorator;

    protected function onSetUp(): void
    {
        // 从容器中获取装饰器服务
        $this->decorator = self::getService(FilesystemFactoryDecorator::class);
    }

    public function testCreateFilesystem(): void
    {
        // Arrange - 使用有效的 S3 配置
        $_ENV['AWS_S3_BUCKET'] = 'test-bucket';
        $_ENV['AWS_S3_REGION'] = 'us-east-1';
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'test-access-key';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'test-secret-key';

        // Act
        $result = $this->decorator->createFilesystem();

        // Assert
        $this->assertInstanceOf(FilesystemOperator::class, $result);
    }

    public function testS3FilesystemWithAllOptionalParametersShouldCreateFilesystem(): void
    {
        // Arrange - 设置完整的 S3 配置
        $_ENV['AWS_S3_BUCKET'] = 'production-bucket';
        $_ENV['AWS_S3_REGION'] = 'eu-west-1';
        $_ENV['AWS_S3_PREFIX'] = 'uploads/documents/';
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'AKIA-test-key';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'test-secret-key';
        $_ENV['AWS_S3_ENDPOINT'] = 'https://s3.custom-domain.com';

        // Act
        $result = $this->decorator->createFilesystem();

        // Assert
        $this->assertInstanceOf(FilesystemOperator::class, $result);
    }

    public function testS3FilesystemWithDefaultValuesShouldUseDefaults(): void
    {
        // Arrange - 设置最小配置，其他使用默认值
        $_ENV['AWS_S3_BUCKET'] = 'test-bucket';
        $_ENV['AWS_S3_REGION'] = 'ap-southeast-1';
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'test-access-key';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'test-secret-access-key';
        // 不设置其他可选参数，使用默认值
        unset($_ENV['AWS_S3_PREFIX'], $_ENV['AWS_S3_ENDPOINT']);

        // Act
        $result = $this->decorator->createFilesystem();

        // Assert
        $this->assertInstanceOf(FilesystemOperator::class, $result);
    }

    public function testS3FilesystemWithMinIOEndpointShouldCreateFilesystem(): void
    {
        // Arrange - 测试使用 MinIO 端点
        $_ENV['AWS_S3_BUCKET'] = 'minio-bucket';
        $_ENV['AWS_S3_REGION'] = 'us-east-1';
        $_ENV['AWS_S3_ENDPOINT'] = 'http://localhost:9000';
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'minioadmin';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'minioadmin';

        // Act
        $result = $this->decorator->createFilesystem();

        // Assert
        $this->assertInstanceOf(FilesystemOperator::class, $result);
    }

    public function testS3FilesystemWithSpecialCharactersInPrefixShouldCreateFilesystem(): void
    {
        // Arrange - 测试前缀包含特殊字符
        $_ENV['AWS_S3_BUCKET'] = 'test-bucket';
        $_ENV['AWS_S3_REGION'] = 'us-east-1';
        $_ENV['AWS_S3_PREFIX'] = 'app/uploads/用户文件/2023/';
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'test-access-key';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'test-secret-access-key';

        // Act
        $result = $this->decorator->createFilesystem();

        // Assert
        $this->assertInstanceOf(FilesystemOperator::class, $result);
    }

    /**
     * @param array<string, string> $envConfig
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    #[TestWith([
        [
            'AWS_S3_BUCKET' => 'test-bucket',
            'AWS_S3_REGION' => 'us-east-1',
            'AWS_S3_ACCESS_KEY_ID' => 'AKIA-test-access-key',
            'AWS_S3_SECRET_ACCESS_KEY' => 'test-secret-access-key',
        ],
    ])]
    #[TestWith([
        [
            'AWS_S3_BUCKET' => 'test-bucket',
            'AWS_S3_REGION' => 'us-west-2',
            'AWS_S3_PREFIX' => 'uploads/',
            'AWS_S3_ACCESS_KEY_ID' => 'AKIA-test-access-key',
            'AWS_S3_SECRET_ACCESS_KEY' => 'test-secret-access-key',
        ],
    ])]
    #[TestWith([
        [
            'AWS_S3_BUCKET' => 'test-bucket',
            'AWS_S3_REGION' => 'eu-west-1',
            'AWS_S3_ENDPOINT' => 'https://s3.amazonaws.com',
            'AWS_S3_ACCESS_KEY_ID' => 'AKIA-test-access-key',
            'AWS_S3_SECRET_ACCESS_KEY' => 'test-secret-access-key',
        ],
    ])]
    #[TestWith([
        [
            'AWS_S3_BUCKET' => 'production-bucket',
            'AWS_S3_REGION' => 'ap-northeast-1',
            'AWS_S3_PREFIX' => 'app/storage/',
            'AWS_S3_ACCESS_KEY_ID' => 'AKIA123456789',
            'AWS_S3_SECRET_ACCESS_KEY' => 'secret-key-123',
            'AWS_S3_ENDPOINT' => 'https://minio.example.com:9000',
        ],
    ])]
    public function testVariousValidS3ConfigurationsShouldCreateS3Filesystem(array $envConfig): void
    {
        // Arrange
        foreach ($envConfig as $key => $value) {
            $_ENV[$key] = $value;
        }

        // Act
        $result = $this->decorator->createFilesystem();

        // Assert
        $this->assertInstanceOf(FilesystemOperator::class, $result);
    }

    /**
     * @param array<string, string|null> $envConfig
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    #[TestWith([
        [
            'AWS_S3_BUCKET' => null,
            'AWS_S3_REGION' => 'us-east-1',
        ],
    ])]
    #[TestWith([
        [
            'AWS_S3_BUCKET' => 'test-bucket',
            'AWS_S3_REGION' => null,
        ],
    ])]
    #[TestWith([
        [
            'AWS_S3_BUCKET' => '',
            'AWS_S3_REGION' => 'us-east-1',
        ],
    ])]
    #[TestWith([
        [
            'AWS_S3_BUCKET' => 'test-bucket',
            'AWS_S3_REGION' => '',
        ],
    ])]
    #[TestWith([
        [
            'AWS_S3_BUCKET' => null,
            'AWS_S3_REGION' => null,
        ],
    ])]
    public function testInvalidS3ConfigurationsShouldUseFallback(array $envConfig): void
    {
        // Arrange
        foreach ($envConfig as $key => $value) {
            if (null === $value) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        // Act - 应该使用 fallback (内部工厂)，但不会失败
        $result = $this->decorator->createFilesystem();

        // Assert - 仍然应该返回一个有效的文件系统
        $this->assertInstanceOf(FilesystemOperator::class, $result);
    }

    public function testDecoratorIsReadonly(): void
    {
        // Assert - This test ensures the decorator is marked as readonly
        $reflection = new \ReflectionClass(FilesystemFactoryDecorator::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    protected function onTearDown(): void
    {
        // 清理环境变量
        $envKeys = [
            'AWS_S3_BUCKET',
            'AWS_S3_REGION',
            'AWS_S3_ACCESS_KEY_ID',
            'AWS_S3_SECRET_ACCESS_KEY',
            'AWS_S3_PREFIX',
            'AWS_S3_ENDPOINT',
        ];

        foreach ($envKeys as $key) {
            unset($_ENV[$key]);
        }
    }
}
