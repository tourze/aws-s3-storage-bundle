<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Adapter;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\AwsS3StorageBundle\Adapter\AwsS3Adapter;
use Tourze\AwsS3StorageBundle\Client\S3ClientInterface;
use Tourze\AwsS3StorageBundle\Exception\ConfigurationException;
use Tourze\AwsS3StorageBundle\Exception\S3Exception;

/**
 * @internal
 */
#[CoversClass(AwsS3Adapter::class)]
final class AwsS3AdapterTest extends TestCase
{
    private S3ClientInterface&MockObject $client;

    private string $bucket;

    private AwsS3Adapter $adapter;

    protected function setUp(): void
    {
        $this->client = $this->createMock(S3ClientInterface::class);
        $this->bucket = 'test-bucket';
        $this->adapter = new AwsS3Adapter($this->client, $this->bucket);
    }

    public function testConstructorWithValidParametersShouldCreateAdapter(): void
    {
        // Act
        $adapter = new AwsS3Adapter($this->client, $this->bucket);

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testConstructorWithPrefixShouldCreateAdapter(): void
    {
        // Act
        $adapter = new AwsS3Adapter($this->client, $this->bucket, 'uploads/');

        // Assert
        $this->assertInstanceOf(AwsS3Adapter::class, $adapter);
    }

    public function testConstructorWithEmptyBucketShouldThrowConfigurationException(): void
    {
        // Act & Assert
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Bucket name cannot be empty');

        new AwsS3Adapter($this->client, '');
    }

    public function testFileExistsWithExistingFileShouldReturnTrue(): void
    {
        // Arrange
        $path = 'test-file.txt';

        $this->client->method('headObject')
            ->with($this->bucket, $path)
            ->willReturn(['ContentLength' => 1024])
        ;

        // Act
        $result = $this->adapter->fileExists($path);

        // Assert
        $this->assertTrue($result);
    }

    public function testFileExistsWithNonExistingFileShouldReturnFalse(): void
    {
        // Arrange
        $path = 'non-existing.txt';

        $this->client->method('headObject')
            ->with($this->bucket, $path)
            ->willThrowException(new \RuntimeException('File not found'))
        ;

        // Act
        $result = $this->adapter->fileExists($path);

        // Assert
        $this->assertFalse($result);
    }

    public function testWriteWithValidContentShouldSucceed(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $contents = 'Hello, World!';
        $config = new Config();

        $this->client->expects($this->once())
            ->method('putObject')
            ->with($this->bucket, $path, $contents, [])
            ->willReturn(['ETag' => '"abc123"'])
        ;

        // Act & Assert - Should not throw exception
        $this->adapter->write($path, $contents, $config);
    }

    public function testWriteWithExceptionShouldThrowUnableToWriteFile(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $contents = 'Hello, World!';
        $config = new Config();

        $this->client->method('putObject')
            ->willThrowException(new S3Exception('Upload failed'))
        ;

        // Act & Assert
        $this->expectException(UnableToWriteFile::class);

        $this->adapter->write($path, $contents, $config);
    }

    public function testReadWithExistingFileShouldReturnContent(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $expectedContent = 'File content';

        $this->client->method('getObject')
            ->with($this->bucket, $path, [])
            ->willReturn([
                'Body' => $expectedContent,
                'ContentLength' => strlen($expectedContent),
                'ContentType' => 'text/plain',
            ])
        ;

        // Act
        $result = $this->adapter->read($path);

        // Assert
        $this->assertEquals($expectedContent, $result);
    }

    public function testReadWithNonExistingFileShouldThrowUnableToReadFile(): void
    {
        // Arrange
        $path = 'non-existing.txt';

        $this->client->method('getObject')
            ->willThrowException(new S3Exception('File not found'))
        ;

        // Act & Assert
        $this->expectException(UnableToReadFile::class);

        $this->adapter->read($path);
    }

    public function testDeleteWithExistingFileShouldSucceed(): void
    {
        // Arrange
        $path = 'test-file.txt';

        $this->client->expects($this->once())
            ->method('deleteObject')
            ->with($this->bucket, $path)
            ->willReturn(['DeleteMarker' => false])
        ;

        // Act & Assert - Should not throw exception
        $this->adapter->delete($path);
    }

    public function testDeleteWithExceptionShouldThrowUnableToDeleteFile(): void
    {
        // Arrange
        $path = 'test-file.txt';

        $this->client->method('deleteObject')
            ->willThrowException(new S3Exception('Delete failed'))
        ;

        // Act & Assert
        $this->expectException(UnableToDeleteFile::class);

        $this->adapter->delete($path);
    }

    public function testSetVisibilityShouldThrowUnableToSetVisibility(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $visibility = Visibility::PUBLIC;

        // Act & Assert
        $this->expectException(UnableToSetVisibility::class);
        $this->expectExceptionMessage('AWS S3 does not support visibility changes through ACL.');

        $this->adapter->setVisibility($path, $visibility);
    }

    public function testVisibilityShouldReturnPrivateByDefault(): void
    {
        // Arrange
        $path = 'test-file.txt';

        // Act
        $result = $this->adapter->visibility($path);

        // Assert
        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertEquals($path, $result->path());
        $this->assertEquals(Visibility::PRIVATE, $result->visibility());
    }

    public function testMimeTypeWithValidFileShouldReturnMimeType(): void
    {
        // Arrange
        $path = 'test-file.txt';

        $this->client->method('headObject')
            ->with($this->bucket, $path)
            ->willReturn(['ContentType' => 'text/plain'])
        ;

        // Act
        $result = $this->adapter->mimeType($path);

        // Assert
        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertEquals($path, $result->path());
        $this->assertEquals('text/plain', $result->mimeType());
    }

    public function testMimeTypeWithExceptionShouldThrowUnableToRetrieveMetadata(): void
    {
        // Arrange
        $path = 'test-file.txt';

        $this->client->method('headObject')
            ->willThrowException(new S3Exception('File not found'))
        ;

        // Act & Assert
        $this->expectException(UnableToRetrieveMetadata::class);

        $this->adapter->mimeType($path);
    }

    public function testFileSizeWithValidFileShouldReturnSize(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $expectedSize = 1024;

        $this->client->method('headObject')
            ->with($this->bucket, $path)
            ->willReturn(['ContentLength' => (string) $expectedSize])
        ;

        // Act
        $result = $this->adapter->fileSize($path);

        // Assert
        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertEquals($path, $result->path());
        $this->assertEquals($expectedSize, $result->fileSize());
    }

    public function testCopyWithValidSourceAndDestinationShouldSucceed(): void
    {
        // Arrange
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->client->expects($this->once())
            ->method('copyObject')
            ->with($this->bucket, $source, $this->bucket, $destination, [])
            ->willReturn(['ETag' => '"abc123"'])
        ;

        // Act & Assert - Should not throw exception
        $this->adapter->copy($source, $destination, $config);
    }

    public function testCopyWithExceptionShouldThrowUnableToCopyFile(): void
    {
        // Arrange
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->client->method('copyObject')
            ->willThrowException(new S3Exception('Copy failed'))
        ;

        // Act & Assert
        $this->expectException(UnableToCopyFile::class);

        $this->adapter->copy($source, $destination, $config);
    }

    public function testDirectoryExistsWithExistingDirectoryShouldReturnTrue(): void
    {
        // Arrange
        $path = 'test-directory';

        $this->client->method('listObjects')
            ->willReturn(['Contents' => [['Key' => 'test-directory/file.txt']]])
        ;

        // Act
        $result = $this->adapter->directoryExists($path);

        // Assert
        $this->assertTrue($result);
    }

    public function testCreateDirectoryShouldCreateMarkerObject(): void
    {
        // Arrange
        $path = 'new-directory';
        $config = new Config();

        $this->client->expects($this->once())
            ->method('putObject')
            ->willReturn(['ETag' => '"abc123"'])
        ;

        // Act & Assert - Should not throw exception
        $this->adapter->createDirectory($path, $config);
    }

    public function testDeleteDirectoryWithObjectsShouldDeleteAll(): void
    {
        // Arrange
        $path = 'test-directory';

        $this->client->method('listObjects')
            ->willReturn([
                'Contents' => [
                    ['Key' => 'test-directory/file1.txt'],
                    ['Key' => 'test-directory/file2.txt'],
                ],
                'NextContinuationToken' => null,
            ])
        ;

        $this->client->expects($this->once())
            ->method('deleteObjects')
            ->willReturn(['Deleted' => [], 'Errors' => []])
        ;

        // Act & Assert - Should not throw exception
        $this->adapter->deleteDirectory($path);
    }

    public function testLastModifiedWithValidFileShouldReturnTimestamp(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $lastModified = '2023-01-01T12:00:00+00:00';

        $this->client->method('headObject')
            ->willReturn(['LastModified' => $lastModified])
        ;

        // Act
        $result = $this->adapter->lastModified($path);

        // Assert
        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertEquals($path, $result->path());
        $this->assertEquals(strtotime($lastModified), $result->lastModified());
    }

    public function testListContentsWithShallowListingShouldReturnFilesAndDirectories(): void
    {
        // Arrange
        $path = 'test-directory';
        $deep = false;

        $this->client->method('listObjects')
            ->willReturn([
                'Contents' => [
                    [
                        'Key' => 'test-directory/file1.txt',
                        'Size' => 1024,
                        'LastModified' => '2023-01-01T12:00:00+00:00',
                        'ETag' => '"abc123"',
                    ],
                ],
                'CommonPrefixes' => [
                    ['Prefix' => 'test-directory/subdir/'],
                ],
                'NextContinuationToken' => null,
            ])
        ;

        // Act
        $result = iterator_to_array($this->adapter->listContents($path, $deep));

        // Assert
        $this->assertCount(2, $result); // 1 file + 1 directory

        $files = array_filter($result, fn ($item) => $item instanceof FileAttributes);
        $directories = array_filter($result, fn ($item) => $item instanceof DirectoryAttributes);

        $this->assertCount(1, $files);
        $this->assertCount(1, $directories);
    }

    public function testMoveWithValidSourceAndDestinationShouldCopyAndDelete(): void
    {
        // Arrange
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->client->expects($this->once())
            ->method('copyObject')
            ->willReturn(['ETag' => '"abc123"'])
        ;

        $this->client->expects($this->once())
            ->method('deleteObject')
            ->willReturn(['DeleteMarker' => false])
        ;

        // Act & Assert - Should not throw exception
        $this->adapter->move($source, $destination, $config);
    }

    public function testReadStreamWithExistingFileShouldReturnStream(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $expectedContent = 'File content';

        $this->client->method('getObject')
            ->willReturn([
                'Body' => $expectedContent,
                'ContentLength' => strlen($expectedContent),
                'ContentType' => 'text/plain',
            ])
        ;

        // Act
        $result = $this->adapter->readStream($path);

        // Assert
        $this->assertIsResource($result);
        $content = stream_get_contents($result);
        $this->assertEquals($expectedContent, $content);

        fclose($result);
    }

    public function testWriteStreamWithValidStreamShouldSucceed(): void
    {
        // Arrange
        $path = 'test-file.txt';
        $contents = fopen('php://memory', 'r+');
        $this->assertIsResource($contents, 'Failed to open stream');
        fwrite($contents, 'Stream content');
        rewind($contents);
        $config = new Config();

        $this->client->expects($this->once())
            ->method('putObject')
            ->willReturn(['ETag' => '"abc123"'])
        ;

        // Act & Assert - Should not throw exception
        $this->adapter->writeStream($path, $contents, $config);

        fclose($contents);
    }
}
