<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Helper;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\PathPrefixer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\AwsS3StorageBundle\Client\S3ClientInterface;
use Tourze\AwsS3StorageBundle\Helper\S3OperationHelper;

/**
 * @internal
 */
#[CoversClass(S3OperationHelper::class)]
class S3OperationHelperTest extends TestCase
{
    private S3ClientInterface&MockObject $client;

    private PathPrefixer $prefixer;

    private S3OperationHelper $helper;

    protected function setUp(): void
    {
        $this->client = $this->createMock(S3ClientInterface::class);
        $this->prefixer = new PathPrefixer('test/');
        $this->helper = new S3OperationHelper($this->client, $this->prefixer);
    }

    public function testDeleteDirectoryObjectsWithEmptyObjects(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $location = 'test/path/';

        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with($bucket, ['prefix' => $location])
            ->willReturn(['Contents' => []])
        ;

        $this->client
            ->expects($this->never())
            ->method('deleteObjects')
        ;

        // Act
        $this->helper->deleteDirectoryObjects($bucket, $location);
    }

    public function testDeleteDirectoryObjectsWithObjects(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $location = 'test/path/';
        $objects = [
            ['Key' => 'test/path/file1.txt'],
            ['Key' => 'test/path/file2.txt'],
        ];

        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with($bucket, ['prefix' => $location])
            ->willReturn(['Contents' => $objects])
        ;

        $this->client
            ->expects($this->once())
            ->method('deleteObjects')
            ->with($bucket, [
                ['Key' => 'test/path/file1.txt'],
                ['Key' => 'test/path/file2.txt'],
            ])
        ;

        // Act
        $this->helper->deleteDirectoryObjects($bucket, $location);
    }

    public function testListAllObjectsWithSinglePage(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $location = 'test/path/';
        $result = [
            'Contents' => [
                ['Key' => 'test/path/file1.txt'],
                ['Key' => 'test/path/file2.txt'],
            ],
        ];

        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with($bucket, ['prefix' => $location])
            ->willReturn($result)
        ;

        // Act
        $objects = $this->helper->listAllObjects($bucket, $location);

        // Assert
        $expected = [
            ['Key' => 'test/path/file1.txt'],
            ['Key' => 'test/path/file2.txt'],
        ];
        $this->assertSame($expected, $objects);
    }

    public function testListAllObjectsWithMultiplePages(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $location = 'test/path/';

        $firstResult = [
            'Contents' => [
                ['Key' => 'test/path/file1.txt'],
            ],
            'NextContinuationToken' => 'token123',
        ];

        $secondResult = [
            'Contents' => [
                ['Key' => 'test/path/file2.txt'],
            ],
        ];

        $this->client
            ->expects($this->exactly(2))
            ->method('listObjects')
            ->willReturnCallback(function ($bucket, $options) use ($firstResult, $secondResult) {
                if (is_array($options) && isset($options['continuation-token'])) {
                    return $secondResult;
                }

                return $firstResult;
            })
        ;

        // Act
        $objects = $this->helper->listAllObjects($bucket, $location);

        // Assert
        $expected = [
            ['Key' => 'test/path/file1.txt'],
            ['Key' => 'test/path/file2.txt'],
        ];
        $this->assertSame($expected, $objects);
    }

    public function testListAllObjectsWithInvalidContents(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $location = 'test/path/';
        $result = [
            'Contents' => [
                ['Key' => 'valid-file.txt'],
                ['InvalidKey' => 'invalid-object'],  // Missing 'Key'
                ['Key' => 123],  // Invalid Key type
            ],
        ];

        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with($bucket, ['prefix' => $location])
            ->willReturn($result)
        ;

        // Act
        $objects = $this->helper->listAllObjects($bucket, $location);

        // Assert
        $expected = [['Key' => 'valid-file.txt']];
        $this->assertSame($expected, $objects);
    }

    public function testListAllObjectsWithNonArrayContents(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $location = 'test/path/';
        $result = ['Contents' => 'invalid-content'];

        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with($bucket, ['prefix' => $location])
            ->willReturn($result)
        ;

        // Act
        $objects = $this->helper->listAllObjects($bucket, $location);

        // Assert
        $this->assertSame([], $objects);
    }

    public function testProcessObjectContentsWithValidObjects(): void
    {
        // Arrange
        $result = [
            'Contents' => [
                [
                    'Key' => 'test/file1.txt',
                    'Size' => 1024,
                    'LastModified' => '2023-01-01T12:00:00Z',
                ],
                [
                    'Key' => 'test/file2.txt',
                    'Size' => '2048',
                    'LastModified' => '2023-01-02T12:00:00Z',
                ],
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertCount(2, $attributes);
        $this->assertInstanceOf(FileAttributes::class, $attributes[0]);
        $this->assertSame('file1.txt', $attributes[0]->path());
        $this->assertSame(1024, $attributes[0]->fileSize());
        $this->assertSame(strtotime('2023-01-01T12:00:00Z'), $attributes[0]->lastModified());

        $this->assertInstanceOf(FileAttributes::class, $attributes[1]);
        $this->assertSame('file2.txt', $attributes[1]->path());
        $this->assertSame(2048, $attributes[1]->fileSize());
        $this->assertSame(strtotime('2023-01-02T12:00:00Z'), $attributes[1]->lastModified());
    }

    public function testProcessObjectContentsSkipsDirectoryMarkers(): void
    {
        // Arrange
        $result = [
            'Contents' => [
                [
                    'Key' => 'test/directory/',
                    'Size' => 0,
                ],
                [
                    'Key' => 'test/file.txt',
                    'Size' => 1024,
                ],
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertCount(1, $attributes);
        $this->assertSame('file.txt', $attributes[0]->path());
    }

    public function testProcessObjectContentsWithInvalidObjects(): void
    {
        // Arrange
        $result = [
            'Contents' => [
                ['InvalidKey' => 'no-key-field'],
                ['Key' => 123],  // Invalid Key type
                ['Key' => 'test/valid-file.txt', 'Size' => 1024],
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertCount(1, $attributes);
        $this->assertSame('valid-file.txt', $attributes[0]->path());
    }

    public function testProcessObjectContentsWithEmptyContents(): void
    {
        // Arrange
        $result = ['Contents' => []];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertEmpty($attributes);
    }

    public function testProcessObjectContentsWithMissingContents(): void
    {
        // Arrange
        $result = [];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertEmpty($attributes);
    }

    public function testProcessObjectContentsWithNonArrayContents(): void
    {
        // Arrange
        $result = ['Contents' => 'invalid'];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertEmpty($attributes);
    }

    public function testProcessObjectContentsWithNullSizeAndLastModified(): void
    {
        // Arrange
        $result = [
            'Contents' => [
                ['Key' => 'test/file.txt'],  // Missing Size and LastModified
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertCount(1, $attributes);
        $this->assertNull($attributes[0]->fileSize());
        $this->assertNull($attributes[0]->lastModified());
    }

    public function testProcessObjectContentsWithInvalidLastModified(): void
    {
        // Arrange
        $result = [
            'Contents' => [
                [
                    'Key' => 'test/file.txt',
                    'LastModified' => 'invalid-date',
                ],
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processObjectContents($result));

        // Assert
        $this->assertCount(1, $attributes);
        $this->assertNull($attributes[0]->lastModified());
    }

    public function testProcessDirectoryPrefixesWhenDeepIsTrue(): void
    {
        // Arrange
        $result = [
            'CommonPrefixes' => [
                ['Prefix' => 'test/dir1/'],
                ['Prefix' => 'test/dir2/'],
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processDirectoryPrefixes($result, true));

        // Assert
        $this->assertEmpty($attributes);
    }

    public function testProcessDirectoryPrefixesWhenDeepIsFalse(): void
    {
        // Arrange
        $result = [
            'CommonPrefixes' => [
                ['Prefix' => 'test/dir1/'],
                ['Prefix' => 'test/dir2/'],
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processDirectoryPrefixes($result, false));

        // Assert
        $this->assertCount(2, $attributes);
        $this->assertInstanceOf(DirectoryAttributes::class, $attributes[0]);
        $this->assertSame('dir1', $attributes[0]->path());
        $this->assertInstanceOf(DirectoryAttributes::class, $attributes[1]);
        $this->assertSame('dir2', $attributes[1]->path());
    }

    public function testProcessDirectoryPrefixesWithEmptyPrefixes(): void
    {
        // Arrange
        $result = ['CommonPrefixes' => []];

        // Act
        $attributes = iterator_to_array($this->helper->processDirectoryPrefixes($result, false));

        // Assert
        $this->assertEmpty($attributes);
    }

    public function testProcessDirectoryPrefixesWithMissingPrefixes(): void
    {
        // Arrange
        $result = [];

        // Act
        $attributes = iterator_to_array($this->helper->processDirectoryPrefixes($result, false));

        // Assert
        $this->assertEmpty($attributes);
    }

    public function testProcessDirectoryPrefixesWithNonArrayPrefixes(): void
    {
        // Arrange
        $result = ['CommonPrefixes' => 'invalid'];

        // Act
        $attributes = iterator_to_array($this->helper->processDirectoryPrefixes($result, false));

        // Assert
        $this->assertEmpty($attributes);
    }

    public function testProcessDirectoryPrefixesWithInvalidPrefixes(): void
    {
        // Arrange
        $result = [
            'CommonPrefixes' => [
                ['InvalidPrefix' => 'test/dir1/'],  // Missing 'Prefix' key
                ['Prefix' => 123],  // Invalid Prefix type
                ['Prefix' => 'test/valid-dir/'],
            ],
        ];

        // Act
        $attributes = iterator_to_array($this->helper->processDirectoryPrefixes($result, false));

        // Assert
        $this->assertCount(1, $attributes);
        $this->assertSame('valid-dir', $attributes[0]->path());
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('extractOptionsFromConfigDataProvider')]
    public function testExtractOptionsFromConfig(Config $config, array $expected): void
    {
        // Act
        $options = $this->helper->extractOptionsFromConfig($config);

        // Assert
        $this->assertSame($expected, $options);
    }

    /**
     * @return array<string, array{Config, array<string, mixed>}>
     */
    public static function extractOptionsFromConfigDataProvider(): array
    {
        return [
            'empty config' => [
                new Config(),
                [],
            ],
            'with content type' => [
                new Config(['ContentType' => 'text/plain']),
                ['ContentType' => 'text/plain'],
            ],
            'with metadata' => [
                new Config(['metadata' => ['custom' => 'value']]),
                ['Metadata' => ['custom' => 'value']],
            ],
            'with both content type and metadata' => [
                new Config([
                    'ContentType' => 'application/json',
                    'metadata' => ['author' => 'test', 'version' => '1.0'],
                ]),
                [
                    'ContentType' => 'application/json',
                    'Metadata' => ['author' => 'test', 'version' => '1.0'],
                ],
            ],
            'with null content type' => [
                new Config(['ContentType' => null]),
                [],
            ],
            'with non-array metadata' => [
                new Config(['metadata' => 'invalid']),
                [],
            ],
            'with other config values' => [
                new Config([
                    'ContentType' => 'image/png',
                    'metadata' => ['type' => 'image'],
                    'visibility' => 'public',  // Should be ignored
                    'other' => 'value',  // Should be ignored
                ]),
                [
                    'ContentType' => 'image/png',
                    'Metadata' => ['type' => 'image'],
                ],
            ],
        ];
    }
}
