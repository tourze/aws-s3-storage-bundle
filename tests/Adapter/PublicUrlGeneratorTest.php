<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Adapter;

use League\Flysystem\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\AwsS3StorageBundle\Adapter\PublicUrlGenerator;
use Tourze\AwsS3StorageBundle\Exception\S3Exception;
use Tourze\AwsS3StorageBundle\Factory\FilesystemFactoryDecorator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * PublicUrlGenerator 集成测试
 *
 * 测试 AWS S3 公共 URL 生成器的核心功能
 * 由于 PublicUrlGenerator 是服务，需要通过工厂或容器来测试
 *
 * @internal
 */
#[CoversClass(PublicUrlGenerator::class)]
#[RunTestsInSeparateProcesses]
class PublicUrlGeneratorTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试不需要特殊设置
    }

    /**
     * 通过环境配置创建 PublicUrlGenerator 实例
     *
     * 这是在集成测试中测试 PublicUrlGenerator 的正确方式，
     * 通过 FilesystemFactoryDecorator 和环境变量间接创建
     */
    private function createGeneratorThroughFactory(
        string $bucket = 'test-bucket',
        string $region = 'us-east-1',
        string $prefix = '',
        ?string $cdnUrl = null,
        ?string $endpoint = null,
    ): PublicUrlGenerator {
        $originalEnv = $_ENV;

        $_ENV['AWS_S3_BUCKET'] = $bucket;
        $_ENV['AWS_S3_REGION'] = $region;
        $_ENV['AWS_S3_PREFIX'] = $prefix;
        // 测试环境使用占位符凭证，避免硬编码敏感信息
        $_ENV['AWS_S3_ACCESS_KEY_ID'] = 'test-key-placeholder';
        $_ENV['AWS_S3_SECRET_ACCESS_KEY'] = 'test-secret-placeholder';

        if (null !== $cdnUrl) {
            $_ENV['AWS_S3_CDN_URL'] = $cdnUrl;
        }
        if (null !== $endpoint) {
            $_ENV['AWS_S3_ENDPOINT'] = $endpoint;
        }

        try {
            $factory = self::getService(FilesystemFactoryDecorator::class);
            $filesystem = $factory->createFilesystem();

            // 通过反射获取 PublicUrlGenerator
            $reflection = new \ReflectionClass($filesystem);
            $property = $reflection->getProperty('publicUrlGenerator');
            $property->setAccessible(true);

            $urlGenerator = $property->getValue($filesystem);
            $this->assertInstanceOf(PublicUrlGenerator::class, $urlGenerator);

            return $urlGenerator;
        } finally {
            $_ENV = $originalEnv;
        }
    }

    public function testConstructorWithS3FormatShouldCreateValidBaseUrl(): void
    {
        // Arrange & Act
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket',
            region: 'us-east-1'
        );

        // Assert
        $config = new Config();
        $url = $generator->publicUrl('test.txt', $config);
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/test.txt', $url);
    }

    public function testConstructorWithCloudFrontFormatShouldCreateValidBaseUrl(): void
    {
        // Arrange & Act
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket',
            cdnUrl: 'd123456.cloudfront.net'
        );

        // Assert
        $config = new Config();
        $url = $generator->publicUrl('test.txt', $config);
        $this->assertEquals('https://d123456.cloudfront.net/test.txt', $url);
    }

    public function testConstructorWithCustomEndpointShouldUseEndpoint(): void
    {
        // Arrange & Act
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket',
            endpoint: 's3.ap-northeast-1.amazonaws.com'
        );

        // Assert
        $config = new Config();
        $url = $generator->publicUrl('test.txt', $config);
        $this->assertEquals('https://my-bucket.s3.ap-northeast-1.amazonaws.com/test.txt', $url);
    }

    public function testPublicUrlWithEmptyPrefixShouldGenerateSimplePath(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $path = 'documents/file.pdf';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/documents/file.pdf', $url);
    }

    public function testPublicUrlWithPrefixShouldCombineWithPath(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket',
            prefix: 'uploads'
        );
        $path = 'documents/file.pdf';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/uploads/documents/file.pdf', $url);
    }

    public function testPublicUrlWithSpecialCharactersShouldUrlEncode(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $path = 'folder with spaces/file name with spaces.txt';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/folder%20with%20spaces/file%20name%20with%20spaces.txt', $url);
    }

    public function testPublicUrlWithUnicodeCharactersShouldUrlEncode(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $path = '中文/文件名.txt';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/%E4%B8%AD%E6%96%87/%E6%96%87%E4%BB%B6%E5%90%8D.txt', $url);
    }

    public function testPublicUrlWithSlashesShouldPreserveSlashes(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $path = 'level1/level2/level3/file.txt';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/level1/level2/level3/file.txt', $url);
    }

    public function testPublicUrlWithCustomDomainShouldUseCustomDomain(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket',
            prefix: 'static',
            cdnUrl: 'files.example.com'
        );
        $path = 'images/logo.png';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://files.example.com/static/images/logo.png', $url);
    }

    public function testPublicUrlWithRootPathShouldHandleCorrectly(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $path = '/';
        $config = new Config();

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Path contains potentially dangerous traversal patterns');
        $generator->publicUrl($path, $config);
    }

    public function testPublicUrlWithDotInPathShouldHandleCorrectly(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $path = 'folder/.hidden/file.txt';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/folder/.hidden/file.txt', $url);
    }

    public function testPublicUrlWithTrailingSlashInPrefixShouldNormalize(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket',
            prefix: 'uploads/'
        );
        $path = 'file.txt';
        $config = new Config();

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/uploads//file.txt', $url);
    }

    #[TestWith(['file with spaces.txt', 'file%20with%20spaces.txt'], 'space character')]
    #[TestWith(['file+name.txt', 'file%2Bname.txt'], 'plus character')]
    #[TestWith(['file&name.txt', 'file%26name.txt'], 'ampersand character')]
    #[TestWith(['file?name.txt', 'file%3Fname.txt'], 'question mark character')]
    #[TestWith(['file#name.txt', 'file%23name.txt'], 'hash character')]
    #[TestWith(['file%name.txt', 'file%25name.txt'], 'percent character')]
    public function testPublicUrlShouldCorrectlyEncodeVariousCharacters(string $input, string $expectedPath): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $config = new Config();

        // Act
        $url = $generator->publicUrl($input, $config);

        // Assert
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/' . $expectedPath, $url);
    }

    public function testPublicUrlIgnoresConfigParameters(): void
    {
        // Arrange
        $generator = $this->createGeneratorThroughFactory(
            bucket: 'my-bucket'
        );
        $path = 'test.txt';
        $config = new Config([
            'some_option' => 'some_value',
            'another_option' => 123,
        ]);

        // Act
        $url = $generator->publicUrl($path, $config);

        // Assert - Config parameters should not affect the URL
        $this->assertEquals('https://my-bucket.s3.us-east-1.amazonaws.com/test.txt', $url);
    }
}
