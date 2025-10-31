<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\AwsS3StorageBundle\Adapter\AwsS3Adapter;
use Tourze\AwsS3StorageBundle\Factory\S3AdapterFactory;

/**
 * @internal
 */
#[CoversClass(S3AdapterFactory::class)]
class S3AdapterFactoryTest extends TestCase
{
    private S3AdapterFactory $factory;

    /** @var array<array<string, mixed>> */
    private const TEST_CONFIGS = [
        [
            'access_key_id' => 'test-key',
            'secret_access_key' => 'test-secret',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
        ],
        [
            'access_key_id' => 'test-key',
            'secret_access_key' => 'test-secret',
            'bucket' => 'test-bucket',
            'region' => 'us-west-2',
            'prefix' => 'uploads',
        ],
        [
            'access_key_id' => 'test-key',
            'secret_access_key' => 'test-secret',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'endpoint' => 'https://s3.custom-domain.com',
        ],
        [
            'access_key_id' => 'AKIATEST123',
            'secret_access_key' => 'secret123/key+test=value',
            'bucket' => 'production-bucket',
            'region' => 'eu-west-1',
            'prefix' => 'app/storage/',
            'endpoint' => 'https://minio.example.com:9000',
        ],
    ];

    protected function setUp(): void
    {
        $this->factory = new S3AdapterFactory();
    }

    public function testCreateWithMinimalParametersShouldReturnAdapter(): void
    {
        // Arrange
        $accessKeyId = 'test-access-key';
        $secretAccessKey = 'test-secret-key';
        $bucket = 'test-bucket';
        $region = 'us-east-1';

        // Act
        $adapter = $this->factory->create($accessKeyId, $secretAccessKey, $bucket, $region);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateWithAllParametersShouldReturnAdapter(): void
    {
        // Arrange
        $accessKeyId = 'test-access-key';
        $secretAccessKey = 'test-secret-key';
        $bucket = 'test-bucket';
        $region = 'us-east-1';
        $prefix = 'uploads/';
        $endpoint = 'http://localhost:9000';

        // Act
        $adapter = $this->factory->create($accessKeyId, $secretAccessKey, $bucket, $region, $prefix, $endpoint);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateWithEmptyPrefixShouldReturnAdapter(): void
    {
        // Arrange
        $accessKeyId = 'test-access-key';
        $secretAccessKey = 'test-secret-key';
        $bucket = 'test-bucket';
        $region = 'us-east-1';
        $prefix = '';

        // Act
        $adapter = $this->factory->create($accessKeyId, $secretAccessKey, $bucket, $region, $prefix);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateWithNullEndpointShouldReturnAdapter(): void
    {
        // Arrange
        $accessKeyId = 'test-access-key';
        $secretAccessKey = 'test-secret-key';
        $bucket = 'test-bucket';
        $region = 'us-east-1';
        $prefix = '';
        $endpoint = null;

        // Act
        $adapter = $this->factory->create($accessKeyId, $secretAccessKey, $bucket, $region, $prefix, $endpoint);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithMinimalConfigShouldReturnAdapter(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithAllConfigShouldReturnAdapter(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'prefix' => 'uploads/',
            'endpoint' => 'http://localhost:9000',
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithEmptyOptionalParametersShouldUseDefaults(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'prefix' => '',
            'endpoint' => null,
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithMissingOptionalParametersShouldUseDefaults(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithCustomEndpointShouldReturnAdapter(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'minio-access-key',
            'secret_access_key' => 'minio-secret-key',
            'bucket' => 'minio-bucket',
            'region' => 'us-east-1',
            'prefix' => 'data/',
            'endpoint' => 'http://minio.local:9000',
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithDifferentRegionsShouldReturnAdapter(): void
    {
        // Arrange
        $regions = ['us-east-1', 'us-west-2', 'eu-west-1', 'ap-southeast-1'];

        foreach ($regions as $region) {
            $config = [
                'access_key_id' => 'test-access-key',
                'secret_access_key' => 'test-secret-key',
                'bucket' => 'test-bucket',
                'region' => $region,
            ];

            // Act
            $adapter = $this->factory->createFromConfig($config);

            // Assert
            $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
        }
    }

    public function testCreateFromConfigWithSpecialCharactersInCredentialsShouldReturnAdapter(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'AKIA+TEST/KEY=123',
            'secret_access_key' => 'Test/Secret+Key/With=Special/Characters',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithLongPrefixShouldReturnAdapter(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'prefix' => 'application/uploads/user-data/files/documents/',
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithEmptyStringsShouldHandleCorrectly(): void
    {
        // Arrange
        $config = [
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'prefix' => '',
            'endpoint' => '',
        ];

        // Act
        $adapter = $this->factory->createFromConfig($config);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testCreateFromConfigWithVariousValidConfigurationsShouldReturnAdapter(): void
    {
        foreach (self::TEST_CONFIGS as $config) {
            // Act
            $adapter = $this->factory->createFromConfig($config);

            // Assert
            $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
        }
    }

    public function testFactoryIsReadonly(): void
    {
        // Assert - This test ensures the factory is marked as readonly
        $reflection = new \ReflectionClass(S3AdapterFactory::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testFactoryCanBeInstantiatedMultipleTimes(): void
    {
        // Act
        $factory1 = new S3AdapterFactory();
        $factory2 = new S3AdapterFactory();

        // Assert
        $this->assertInstanceOf(S3AdapterFactory::class, $factory1);
        $this->assertInstanceOf(S3AdapterFactory::class, $factory2);
        $this->assertNotSame($factory1, $factory2);
    }
}
