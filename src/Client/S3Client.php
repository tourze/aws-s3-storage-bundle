<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Client;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\CopyObjectRequest;
use AsyncAws\S3\Input\DeleteObjectRequest;
use AsyncAws\S3\Input\DeleteObjectsRequest;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Input\HeadObjectRequest;
use AsyncAws\S3\Input\ListObjectsV2Request;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client as AsyncS3Client;
use AsyncAws\S3\ValueObject\Delete;
use AsyncAws\S3\ValueObject\ObjectIdentifier;
use Tourze\AwsS3StorageBundle\Exception\S3Exception;

/**
 * S3 客户端实现
 *
 * 基于 async-aws/s3 的 S3 客户端实现
 */
readonly class S3Client implements S3ClientInterface
{
    public function __construct(
        private AsyncS3Client $client,
    ) {
    }

    public function headObject(string $bucket, string $key): array
    {
        try {
            $request = new HeadObjectRequest([
                'Bucket' => $bucket,
                'Key' => $key,
            ]);

            $result = $this->client->headObject($request);

            return [
                'ContentLength' => $result->getContentLength(),
                'ContentType' => $result->getContentType(),
                'LastModified' => $result->getLastModified()?->format('c'),
                'ETag' => $result->getEtag(),
                'Metadata' => $result->getMetadata(),
            ];
        } catch (HttpException $e) {
            throw new S3Exception('Failed to get object metadata: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            throw new S3Exception('Failed to get object metadata: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new S3Exception('Unexpected error while getting object metadata: ' . $e->getMessage(), 0, $e);
        }
    }

    public function putObject(string $bucket, string $key, string $body, array $options = []): array
    {
        try {
            $contentType = $options['ContentType'] ?? 'application/octet-stream';
            $metadata = $options['Metadata'] ?? [];

            if (!is_string($contentType)) {
                $contentType = 'application/octet-stream';
            }

            if (!is_array($metadata)) {
                $metadata = [];
            } else {
                $metadata = $this->filterMetadata($metadata);
            }

            $request = new PutObjectRequest([
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => $body,
                'ContentType' => $contentType,
                'Metadata' => $metadata,
            ]);

            $result = $this->client->putObject($request);

            return [
                'ETag' => $result->getEtag(),
                'VersionId' => $result->getVersionId() ?? null,
            ];
        } catch (HttpException $e) {
            throw new S3Exception('Failed to put object: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            throw new S3Exception('Failed to put object: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new S3Exception('Unexpected error while putting object: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getObject(string $bucket, string $key, array $options = []): array
    {
        try {
            $request = new GetObjectRequest([
                'Bucket' => $bucket,
                'Key' => $key,
            ]);

            $result = $this->client->getObject($request);

            return [
                'Body' => $result->getBody()->getContentAsString(),
                'ContentLength' => $result->getContentLength(),
                'ContentType' => $result->getContentType(),
                'LastModified' => $result->getLastModified()?->format('c'),
                'ETag' => $result->getEtag(),
                'Metadata' => $result->getMetadata(),
            ];
        } catch (HttpException $e) {
            throw new S3Exception('Failed to get object: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            throw new S3Exception('Failed to get object: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new S3Exception('Unexpected error while getting object: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteObject(string $bucket, string $key): array
    {
        try {
            $request = new DeleteObjectRequest([
                'Bucket' => $bucket,
                'Key' => $key,
            ]);

            $result = $this->client->deleteObject($request);

            return [
                'DeleteMarker' => $result->getDeleteMarker(),
                'VersionId' => $result->getVersionId() ?? null,
            ];
        } catch (HttpException $e) {
            throw new S3Exception('Failed to delete object: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            throw new S3Exception('Failed to delete object: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new S3Exception('Unexpected error while deleting object: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteObjects(string $bucket, array $objects): array
    {
        try {
            $identifiers = [];
            foreach ($objects as $object) {
                if (!is_array($object) || !isset($object['Key']) || !is_string($object['Key'])) {
                    continue;
                }

                $versionId = $object['VersionId'] ?? null;
                if (null !== $versionId && !is_string($versionId)) {
                    $versionId = null;
                }

                $identifiers[] = new ObjectIdentifier([
                    'Key' => $object['Key'],
                    'VersionId' => $versionId,
                ]);
            }

            $delete = new Delete(['Objects' => $identifiers]);

            $request = new DeleteObjectsRequest([
                'Bucket' => $bucket,
                'Delete' => $delete,
            ]);

            $result = $this->client->deleteObjects($request);

            return [
                'Deleted' => $result->getDeleted(),
                'Errors' => $result->getErrors(),
            ];
        } catch (HttpException $e) {
            throw new S3Exception('Failed to delete objects: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            throw new S3Exception('Failed to delete objects: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new S3Exception('Unexpected error while deleting objects: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listObjects(string $bucket, array $options = []): array
    {
        try {
            $prefix = $options['prefix'] ?? '';
            $delimiter = $options['delimiter'] ?? '';
            $maxKeys = $options['max-keys'] ?? 1000;
            $continuationToken = $options['continuation-token'] ?? null;

            if (!is_string($prefix)) {
                $prefix = '';
            }

            if (!is_string($delimiter)) {
                $delimiter = '';
            }

            if (!is_int($maxKeys)) {
                $maxKeys = 1000;
            }

            if (null !== $continuationToken && !is_string($continuationToken)) {
                $continuationToken = null;
            }

            $request = new ListObjectsV2Request([
                'Bucket' => $bucket,
                'Prefix' => $prefix,
                'Delimiter' => $delimiter,
                'MaxKeys' => $maxKeys,
                'ContinuationToken' => $continuationToken,
            ]);

            $result = $this->client->listObjectsV2($request);

            $contents = [];
            foreach ($result->getContents() as $object) {
                $contents[] = [
                    'Key' => $object->getKey(),
                    'Size' => $object->getSize(),
                    'LastModified' => $object->getLastModified()?->format('c'),
                    'ETag' => $object->getEtag(),
                    'StorageClass' => $object->getStorageClass(),
                ];
            }

            $commonPrefixes = [];
            foreach ($result->getCommonPrefixes() as $prefixItem) {
                $commonPrefixes[] = [
                    'Prefix' => $prefixItem->getPrefix(),
                ];
            }

            return [
                'Contents' => $contents,
                'CommonPrefixes' => $commonPrefixes,
                'IsTruncated' => $result->getIsTruncated(),
                'NextContinuationToken' => $result->getNextContinuationToken(),
                'KeyCount' => $result->getKeyCount(),
                'MaxKeys' => $result->getMaxKeys(),
                'Prefix' => $result->getPrefix(),
                'Delimiter' => $result->getDelimiter(),
            ];
        } catch (HttpException $e) {
            throw new S3Exception('Failed to list objects: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            throw new S3Exception('Failed to list objects: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new S3Exception('Unexpected error while listing objects: ' . $e->getMessage(), 0, $e);
        }
    }

    public function copyObject(string $sourceBucket, string $sourceKey, string $destBucket, string $destKey, array $options = []): array
    {
        try {
            $copySource = $sourceBucket . '/' . $sourceKey;

            $contentType = $options['ContentType'] ?? null;
            $metadata = $options['Metadata'] ?? [];
            $metadataDirective = $options['MetadataDirective'] ?? 'COPY';

            if (null !== $contentType && !is_string($contentType)) {
                $contentType = null;
            }

            if (!is_array($metadata)) {
                $metadata = [];
            } else {
                $metadata = $this->filterMetadata($metadata);
            }

            if (!is_string($metadataDirective) || !in_array($metadataDirective, ['COPY', 'REPLACE'], true)) {
                $metadataDirective = 'COPY';
            }

            $request = new CopyObjectRequest([
                'Bucket' => $destBucket,
                'Key' => $destKey,
                'CopySource' => $copySource,
                'ContentType' => $contentType,
                'Metadata' => $metadata,
                'MetadataDirective' => $metadataDirective,
            ]);

            $result = $this->client->copyObject($request);

            return [
                'ETag' => $result->getCopyObjectResult()?->getEtag(),
                'LastModified' => $result->getCopyObjectResult()?->getLastModified()?->format('c'),
                'VersionId' => $result->getVersionId() ?? null,
            ];
        } catch (HttpException $e) {
            throw new S3Exception('Failed to copy object: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            throw new S3Exception('Failed to copy object: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new S3Exception('Unexpected error while copying object: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 过滤并确保 metadata 是字符串键值对数组
     *
     * @param array<mixed> $metadata
     * @return array<string, string>
     */
    private function filterMetadata(array $metadata): array
    {
        $filteredMetadata = [];
        foreach ($metadata as $metaKey => $metaValue) {
            if (is_string($metaKey) && is_string($metaValue)) {
                $filteredMetadata[$metaKey] = $metaValue;
            }
        }

        return $filteredMetadata;
    }
}
