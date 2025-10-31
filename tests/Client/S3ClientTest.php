<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Client;

use AsyncAws\Core\Response;
use AsyncAws\Core\Stream\ResultStream;
use AsyncAws\S3\Result\CopyObjectOutput;
use AsyncAws\S3\Result\DeleteObjectOutput;
use AsyncAws\S3\Result\DeleteObjectsOutput;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\S3\Result\HeadObjectOutput;
use AsyncAws\S3\Result\ListObjectsV2Output;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client as AsyncS3Client;
use AsyncAws\S3\ValueObject\AwsObject as S3Object;
use AsyncAws\S3\ValueObject\CommonPrefix;
use AsyncAws\S3\ValueObject\CopyObjectResult;
use AsyncAws\S3\ValueObject\DeletedObject;
use AsyncAws\S3\ValueObject\Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
// @phpstan-ignore-next-line
use Psr\Log\LoggerInterface;
// @phpstan-ignore-next-line
use Symfony\Contracts\HttpClient\ChunkInterface;
// @phpstan-ignore-next-line
use Symfony\Contracts\HttpClient\HttpClientInterface;
// @phpstan-ignore-next-line
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Tourze\AwsS3StorageBundle\Client\S3Client;
use Tourze\AwsS3StorageBundle\Exception\S3Exception;

/**
 * 创建模拟 Response 对象
 */
function createMockResponse(): Response
{
    // 创建模拟 Response 构造函数所需的最小对象
    $httpResponse = new class implements ResponseInterface {
        public function getStatusCode(): int
        {
            return 200;
        }

        /** @return array<string, list<string>> */
        public function getHeaders(bool $throw = true): array
        {
            return [];
        }

        public function getContent(bool $throw = true): string
        {
            return '';
        }

        /** @return array<string, mixed> */
        public function toArray(bool $throw = true): array
        {
            return [];
        }

        public function getInfo(?string $type = null): mixed
        {
            return null !== $type ? null : ['http_code' => 200];
        }

        public function cancel(): void
        {
        }
    };

    $httpClient = new class implements HttpClientInterface {
        // @phpstan-ignore-next-line
        public function request(string $method, string $url, array $options = []): ResponseInterface
        {
            throw new \RuntimeException('Not implemented in mock');
        }

        public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
        {
            return new class implements ResponseStreamInterface {
                public function current(): ChunkInterface
                {
                    throw new \RuntimeException('Not implemented');
                }

                public function next(): void
                {
                }

                public function key(): ResponseInterface
                {
                    throw new \RuntimeException('Not implemented');
                }

                public function valid(): bool
                {
                    return false;
                }

                public function rewind(): void
                {
                }
            };
        }

        // @phpstan-ignore-next-line
        public function withOptions(array $options): static
        {
            return $this;
        }
    };

    // @phpstan-ignore-next-line
    $logger = new class implements LoggerInterface {
        public function emergency(\Stringable|string $message, array $context = []): void
        {
        }

        public function alert(\Stringable|string $message, array $context = []): void
        {
        }

        public function critical(\Stringable|string $message, array $context = []): void
        {
        }

        public function error(\Stringable|string $message, array $context = []): void
        {
        }

        public function warning(\Stringable|string $message, array $context = []): void
        {
        }

        public function notice(\Stringable|string $message, array $context = []): void
        {
        }

        public function info(\Stringable|string $message, array $context = []): void
        {
        }

        public function debug(\Stringable|string $message, array $context = []): void
        {
        }

        public function log($level, \Stringable|string $message, array $context = []): void
        {
        }
    };

    return new Response($httpResponse, $httpClient, $logger);
}

/**
 * @internal
 */
#[CoversClass(S3Client::class)]
class S3ClientTest extends TestCase
{
    private MockS3Client $asyncClient;

    private S3Client $client;

    protected function setUp(): void
    {
        $this->asyncClient = new MockS3Client();
        $this->client = new S3Client($this->asyncClient);
    }

    public function testHeadObjectWithValidResponseShouldReturnMetadata(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';
        $lastModified = new \DateTimeImmutable('2023-01-01 12:00:00');

        $result = new class extends HeadObjectOutput {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            public function getContentLength(): int
            {
                return 1024;
            }

            public function getContentType(): string
            {
                return 'text/plain';
            }

            public function getLastModified(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2023-01-01 12:00:00');
            }

            public function getEtag(): string
            {
                return '"abc123"';
            }

            public function getMetadata(): array
            {
                return ['custom' => 'value'];
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['headObject']['return'] = $result;

        // Act
        $response = $this->client->headObject($bucket, $key);

        // Assert
        $expected = [
            'ContentLength' => 1024,
            'ContentType' => 'text/plain',
            'LastModified' => '2023-01-01T12:00:00+00:00',
            'ETag' => '"abc123"',
            'Metadata' => ['custom' => 'value'],
        ];
        $this->assertEquals($expected, $response);
    }

    public function testHeadObjectWithNullLastModifiedShouldReturnNullForLastModified(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';

        $result = new class extends HeadObjectOutput {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            public function getContentLength(): int
            {
                return 1024;
            }

            public function getContentType(): string
            {
                return 'text/plain';
            }

            public function getLastModified(): ?\DateTimeImmutable
            {
                return null;
            }

            public function getEtag(): string
            {
                return '"abc123"';
            }

            public function getMetadata(): array
            {
                return [];
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['headObject']['return'] = $result;

        // Act
        $response = $this->client->headObject($bucket, $key);

        // Assert
        $this->assertNull($response['LastModified']);
    }

    public function testHeadObjectWithExceptionShouldThrowS3Exception(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['headObject']['exception'] = new \RuntimeException('Access denied');

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Failed to get object metadata: Access denied');

        $this->client->headObject($bucket, $key);
    }

    public function testPutObjectWithDefaultOptionsShouldUploadSuccessfully(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';
        $body = 'test content';

        $result = new class extends PutObjectOutput {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            public function getEtag(): string
            {
                return '"abc123"';
            }

            public function getVersionId(): string
            {
                return 'version-1';
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['putObject']['return'] = $result;

        // Act
        $response = $this->client->putObject($bucket, $key, $body);

        // Assert
        $expected = [
            'ETag' => '"abc123"',
            'VersionId' => 'version-1',
        ];
        $this->assertEquals($expected, $response);
    }

    public function testPutObjectWithCustomOptionsShouldIncludeOptions(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';
        $body = 'test content';
        $options = [
            'ContentType' => 'text/html',
            'Metadata' => ['author' => 'test'],
        ];

        $result = new class extends PutObjectOutput {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            public function getEtag(): string
            {
                return '"abc123"';
            }

            public function getVersionId(): string
            {
                return 'version-1';
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['putObject']['return'] = $result;

        // Act
        $response = $this->client->putObject($bucket, $key, $body, $options);

        // Assert
        $expected = [
            'ETag' => '"abc123"',
            'VersionId' => 'version-1',
        ];
        $this->assertEquals($expected, $response);
    }

    public function testPutObjectWithExceptionShouldThrowS3Exception(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';
        $body = 'test content';

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['putObject']['exception'] = new \RuntimeException('Upload failed');

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Failed to put object: Upload failed');

        $this->client->putObject($bucket, $key, $body);
    }

    public function testGetObjectWithValidResponseShouldReturnContent(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';
        $lastModified = new \DateTimeImmutable('2023-01-01 12:00:00');

        $stream = new class implements ResultStream {
            public function getContentAsString(): string
            {
                return 'file content';
            }

            /**
             * @return resource
             */
            public function getContentAsResource()
            {
                $resource = fopen('data://text/plain;base64,' . base64_encode('file content'), 'r');
                if (false === $resource) {
                    throw new \RuntimeException('Failed to create resource');
                }

                return $resource;
            }

            public function getChunks(): iterable
            {
                yield 'file content';
            }

            public function __toString(): string
            {
                return 'file content';
            }
        };

        $result = new class($stream) extends GetObjectOutput {
            private ResultStream $stream;

            public function __construct(ResultStream $stream)
            {
                parent::__construct(createMockResponse());
                $this->stream = $stream;
            }

            public function getBody(): ResultStream
            {
                return $this->stream;
            }

            public function getContentLength(): int
            {
                return 12;
            }

            public function getContentType(): string
            {
                return 'text/plain';
            }

            public function getLastModified(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2023-01-01 12:00:00');
            }

            public function getEtag(): string
            {
                return '"abc123"';
            }

            public function getMetadata(): array
            {
                return ['custom' => 'value'];
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['getObject']['return'] = $result;

        // Act
        $response = $this->client->getObject($bucket, $key);

        // Assert
        $expected = [
            'Body' => 'file content',
            'ContentLength' => 12,
            'ContentType' => 'text/plain',
            'LastModified' => '2023-01-01T12:00:00+00:00',
            'ETag' => '"abc123"',
            'Metadata' => ['custom' => 'value'],
        ];
        $this->assertEquals($expected, $response);
    }

    public function testGetObjectWithExceptionShouldThrowS3Exception(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['getObject']['exception'] = new \RuntimeException('File not found');

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Failed to get object: File not found');

        $this->client->getObject($bucket, $key);
    }

    public function testDeleteObjectShouldReturnDeleteResponse(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';

        $result = new class extends DeleteObjectOutput {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            public function getDeleteMarker(): bool
            {
                return true;
            }

            public function getVersionId(): string
            {
                return 'version-1';
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['deleteObject']['return'] = $result;

        // Act
        $response = $this->client->deleteObject($bucket, $key);

        // Assert
        $expected = [
            'DeleteMarker' => true,
            'VersionId' => 'version-1',
        ];
        $this->assertEquals($expected, $response);
    }

    public function testDeleteObjectWithExceptionShouldThrowS3Exception(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $key = 'test-file.txt';

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['deleteObject']['exception'] = new \RuntimeException('Delete failed');

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Failed to delete object: Delete failed');

        $this->client->deleteObject($bucket, $key);
    }

    public function testDeleteObjectsWithValidObjectsShouldReturnBulkDeleteResponse(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $objects = [
            ['Key' => 'file1.txt'],
            ['Key' => 'file2.txt', 'VersionId' => 'version-1'],
        ];

        $deletedObject1 = new DeletedObject([
            'Key' => 'file1.txt',
        ]);

        $deletedObject2 = new DeletedObject([
            'Key' => 'file2.txt',
        ]);

        $error = new Error([
            'Key' => 'file3.txt',
            'Code' => 'NoSuchKey',
        ]);

        $result = new class($deletedObject1, $deletedObject2, $error) extends DeleteObjectsOutput {
            /** @var DeletedObject[] */
            private array $deletedObjects;

            /** @var Error[] */
            private array $errors;

            public function __construct(DeletedObject $obj1, DeletedObject $obj2, Error $error)
            {
                parent::__construct(createMockResponse());
                $this->deletedObjects = [$obj1, $obj2];
                $this->errors = [$error];
            }

            /**
             * @return DeletedObject[]
             */
            public function getDeleted(bool $currentPageOnly = false): array
            {
                return $this->deletedObjects;
            }

            /**
             * @return Error[]
             */
            public function getErrors(bool $currentPageOnly = false): array
            {
                return $this->errors;
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['deleteObjects']['return'] = $result;

        // Act
        $response = $this->client->deleteObjects($bucket, $objects);

        // Assert
        $this->assertArrayHasKey('Deleted', $response);
        $this->assertArrayHasKey('Errors', $response);
        $this->assertIsArray($response['Deleted']);
        $this->assertIsArray($response['Errors']);
        $this->assertCount(2, $response['Deleted']);
        $this->assertCount(1, $response['Errors']);
    }

    public function testDeleteObjectsWithEmptyObjectsShouldCreateEmptyRequest(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $objects = [];

        $result = new class extends DeleteObjectsOutput {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            /**
             * @return array<DeletedObject>
             */
            public function getDeleted(bool $currentPageOnly = false): array
            {
                return [];
            }

            /**
             * @return array<Error>
             */
            public function getErrors(bool $currentPageOnly = false): array
            {
                return [];
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['deleteObjects']['return'] = $result;

        // Act
        $response = $this->client->deleteObjects($bucket, $objects);

        // Assert
        $this->assertEquals([], $response['Deleted']);
        $this->assertEquals([], $response['Errors']);
    }

    public function testDeleteObjectsWithExceptionShouldThrowS3Exception(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $objects = [['Key' => 'file1.txt']];

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['deleteObjects']['exception'] = new \RuntimeException('Batch delete failed');

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Failed to delete objects: Batch delete failed');

        $this->client->deleteObjects($bucket, $objects);
    }

    public function testListObjectsWithDefaultOptionsShouldReturnObjectList(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $lastModified = new \DateTimeImmutable('2023-01-01 12:00:00');

        $object1 = new S3Object([
            'Key' => 'file1.txt',
            'Size' => 1024,
            'LastModified' => $lastModified,
            'ETag' => '"abc123"',
            'StorageClass' => 'STANDARD',
        ]);

        $result = new class($object1) extends ListObjectsV2Output {
            /** @var S3Object[] */
            private array $contents;

            public function __construct(S3Object $object1)
            {
                parent::__construct(createMockResponse());
                $this->contents = [$object1];
            }

            /**
             * @return S3Object[]
             */
            public function getContents(bool $currentPageOnly = false): array
            {
                return $this->contents;
            }

            /**
             * @return iterable<CommonPrefix>
             */
            public function getCommonPrefixes(bool $currentPageOnly = false): iterable
            {
                return [];
            }

            public function getIsTruncated(): bool
            {
                return false;
            }

            public function getNextContinuationToken(): ?string
            {
                return null;
            }

            public function getKeyCount(): int
            {
                return 1;
            }

            public function getMaxKeys(): int
            {
                return 1000;
            }

            public function getPrefix(): string
            {
                return '';
            }

            public function getDelimiter(): string
            {
                return '';
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['listObjectsV2']['return'] = $result;

        // Act
        $response = $this->client->listObjects($bucket);

        // Assert
        $this->assertArrayHasKey('Contents', $response);
        $this->assertArrayHasKey('CommonPrefixes', $response);
        $this->assertIsArray($response['Contents']);
        $this->assertCount(1, $response['Contents']);
        $this->assertIsArray($response['Contents'][0]);
        $this->assertEquals('file1.txt', $response['Contents'][0]['Key']);
        $this->assertEquals(1024, $response['Contents'][0]['Size']);
        $this->assertEquals('2023-01-01T12:00:00+00:00', $response['Contents'][0]['LastModified']);
    }

    public function testListObjectsWithCustomOptionsShouldUseProvidedOptions(): void
    {
        // Arrange
        $bucket = 'test-bucket';
        $options = [
            'prefix' => 'documents/',
            'delimiter' => '/',
            'max-keys' => 100,
            'continuation-token' => 'token123',
        ];

        $result = new class extends ListObjectsV2Output {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            /**
             * @return iterable<S3Object>
             */
            public function getContents(bool $currentPageOnly = false): iterable
            {
                return [];
            }

            /**
             * @return iterable<CommonPrefix>
             */
            public function getCommonPrefixes(bool $currentPageOnly = false): iterable
            {
                return [];
            }

            public function getIsTruncated(): bool
            {
                return false;
            }

            public function getNextContinuationToken(): ?string
            {
                return null;
            }

            public function getKeyCount(): int
            {
                return 0;
            }

            public function getMaxKeys(): int
            {
                return 100;
            }

            public function getPrefix(): string
            {
                return 'documents/';
            }

            public function getDelimiter(): string
            {
                return '/';
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['listObjectsV2']['return'] = $result;

        // Act
        $response = $this->client->listObjects($bucket, $options);

        // Assert
        $this->assertEquals('documents/', $response['Prefix']);
        $this->assertEquals('/', $response['Delimiter']);
        $this->assertEquals(100, $response['MaxKeys']);
    }

    public function testListObjectsWithExceptionShouldThrowS3Exception(): void
    {
        // Arrange
        $bucket = 'test-bucket';

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['listObjectsV2']['exception'] = new \RuntimeException('List failed');

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Failed to list objects: List failed');

        $this->client->listObjects($bucket);
    }

    public function testCopyObjectWithDefaultOptionsShouldCopySuccessfully(): void
    {
        // Arrange
        $sourceBucket = 'source-bucket';
        $sourceKey = 'source-file.txt';
        $destBucket = 'dest-bucket';
        $destKey = 'dest-file.txt';
        $lastModified = new \DateTimeImmutable('2023-01-01 12:00:00');

        $copyResult = new CopyObjectResult([
            'ETag' => '"abc123"',
            'LastModified' => $lastModified,
        ]);

        $result = new class($copyResult) extends CopyObjectOutput {
            private CopyObjectResult $copyResult;

            public function __construct(CopyObjectResult $copyResult)
            {
                parent::__construct(createMockResponse());
                $this->copyResult = $copyResult;
            }

            public function getCopyObjectResult(): CopyObjectResult
            {
                return $this->copyResult;
            }

            public function getVersionId(): string
            {
                return 'version-1';
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['copyObject']['return'] = $result;

        // Act
        $response = $this->client->copyObject($sourceBucket, $sourceKey, $destBucket, $destKey);

        // Assert
        $expected = [
            'ETag' => '"abc123"',
            'LastModified' => '2023-01-01T12:00:00+00:00',
            'VersionId' => 'version-1',
        ];
        $this->assertEquals($expected, $response);
    }

    public function testCopyObjectWithNullCopyResultShouldReturnNulls(): void
    {
        // Arrange
        $sourceBucket = 'source-bucket';
        $sourceKey = 'source-file.txt';
        $destBucket = 'dest-bucket';
        $destKey = 'dest-file.txt';

        $result = new class extends CopyObjectOutput {
            public function __construct()
            {
                parent::__construct(createMockResponse());
            }

            public function getCopyObjectResult(): ?CopyObjectResult
            {
                return null;
            }

            public function getVersionId(): string
            {
                return 'version-1';
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['copyObject']['return'] = $result;

        // Act
        $response = $this->client->copyObject($sourceBucket, $sourceKey, $destBucket, $destKey);

        // Assert
        $expected = [
            'ETag' => null,
            'LastModified' => null,
            'VersionId' => 'version-1',
        ];
        $this->assertEquals($expected, $response);
    }

    public function testCopyObjectWithCustomOptionsShouldUseProvidedOptions(): void
    {
        // Arrange
        $sourceBucket = 'source-bucket';
        $sourceKey = 'source-file.txt';
        $destBucket = 'dest-bucket';
        $destKey = 'dest-file.txt';
        $options = [
            'ContentType' => 'text/html',
            'Metadata' => ['custom' => 'value'],
            'MetadataDirective' => 'REPLACE',
        ];

        $copyResult = new CopyObjectResult([
            'ETag' => '"abc123"',
            'LastModified' => null,
        ]);

        $result = new class($copyResult) extends CopyObjectOutput {
            private CopyObjectResult $copyResult;

            public function __construct(CopyObjectResult $copyResult)
            {
                parent::__construct(createMockResponse());
                $this->copyResult = $copyResult;
            }

            public function getCopyObjectResult(): CopyObjectResult
            {
                return $this->copyResult;
            }

            public function getVersionId(): ?string
            {
                return null;
            }
        };

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['copyObject']['return'] = $result;

        // Act
        $response = $this->client->copyObject($sourceBucket, $sourceKey, $destBucket, $destKey, $options);

        // Assert
        $this->assertEquals('"abc123"', $response['ETag']);
    }

    public function testCopyObjectWithExceptionShouldThrowS3Exception(): void
    {
        // Arrange
        $sourceBucket = 'source-bucket';
        $sourceKey = 'source-file.txt';
        $destBucket = 'dest-bucket';
        $destKey = 'dest-file.txt';

        // @phpstan-ignore-next-line
        $this->asyncClient->expectations['copyObject']['exception'] = new \RuntimeException('Copy failed');

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage('Failed to copy object: Copy failed');

        $this->client->copyObject($sourceBucket, $sourceKey, $destBucket, $destKey);
    }
}
