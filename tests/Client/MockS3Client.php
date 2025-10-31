<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Client;

use AsyncAws\S3\Result\CopyObjectOutput;
use AsyncAws\S3\Result\DeleteObjectOutput;
use AsyncAws\S3\Result\DeleteObjectsOutput;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\S3\Result\HeadObjectOutput;
use AsyncAws\S3\Result\ListObjectsV2Output;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client as AsyncS3Client;

/**
 * 测试用的Mock S3Client
 *
 * @internal
 */
class MockS3Client extends AsyncS3Client
{
    /** @var array<string, array{exception?: \Throwable, return?: object}> */
    public array $expectations = [];

    /** @var array<array{method: string, input: mixed}> */
    public array $callHistory = [];

    public function __construct()
    {
        // Mock client 不需要真实凭证，使用空配置
        $configuration = [
            'accessKeyId' => null,
            'accessKeySecret' => null,
            'region' => null,
        ];
        parent::__construct($configuration);
    }

    public function headObject($input): HeadObjectOutput
    {
        $this->callHistory[] = ['method' => 'headObject', 'input' => $input];
        if (isset($this->expectations['headObject']['exception'])) {
            $exception = $this->expectations['headObject']['exception'];
            if ($exception instanceof \Throwable) {
                throw $exception;
            }
        }

        $return = $this->expectations['headObject']['return'] ?? null;

        return $return instanceof HeadObjectOutput ? $return : new HeadObjectOutput(createMockResponse());
    }

    public function putObject($input): PutObjectOutput
    {
        $this->callHistory[] = ['method' => 'putObject', 'input' => $input];
        if (isset($this->expectations['putObject']['exception'])) {
            $exception = $this->expectations['putObject']['exception'];
            if ($exception instanceof \Throwable) {
                throw $exception;
            }
        }

        $return = $this->expectations['putObject']['return'] ?? null;

        return $return instanceof PutObjectOutput ? $return : new PutObjectOutput(createMockResponse());
    }

    public function getObject($input): GetObjectOutput
    {
        $this->callHistory[] = ['method' => 'getObject', 'input' => $input];
        if (isset($this->expectations['getObject']['exception'])) {
            $exception = $this->expectations['getObject']['exception'];
            if ($exception instanceof \Throwable) {
                throw $exception;
            }
        }

        $return = $this->expectations['getObject']['return'] ?? null;

        return $return instanceof GetObjectOutput ? $return : new GetObjectOutput(createMockResponse());
    }

    public function deleteObject($input): DeleteObjectOutput
    {
        $this->callHistory[] = ['method' => 'deleteObject', 'input' => $input];
        if (isset($this->expectations['deleteObject']['exception'])) {
            $exception = $this->expectations['deleteObject']['exception'];
            if ($exception instanceof \Throwable) {
                throw $exception;
            }
        }

        $return = $this->expectations['deleteObject']['return'] ?? null;

        return $return instanceof DeleteObjectOutput ? $return : new DeleteObjectOutput(createMockResponse());
    }

    // @phpstan-ignore-next-line
    public function deleteObjects($input): DeleteObjectsOutput
    {
        $this->callHistory[] = ['method' => 'deleteObjects', 'input' => $input];
        if (isset($this->expectations['deleteObjects']['exception'])) {
            $exception = $this->expectations['deleteObjects']['exception'];
            if ($exception instanceof \Throwable) {
                throw $exception;
            }
        }

        $return = $this->expectations['deleteObjects']['return'] ?? null;

        return $return instanceof DeleteObjectsOutput ? $return : new DeleteObjectsOutput(createMockResponse());
    }

    public function listObjectsV2($input): ListObjectsV2Output
    {
        $this->callHistory[] = ['method' => 'listObjectsV2', 'input' => $input];
        if (isset($this->expectations['listObjectsV2']['exception'])) {
            $exception = $this->expectations['listObjectsV2']['exception'];
            if ($exception instanceof \Throwable) {
                throw $exception;
            }
        }

        $return = $this->expectations['listObjectsV2']['return'] ?? null;

        return $return instanceof ListObjectsV2Output ? $return : new ListObjectsV2Output(createMockResponse());
    }

    public function copyObject($input): CopyObjectOutput
    {
        $this->callHistory[] = ['method' => 'copyObject', 'input' => $input];
        if (isset($this->expectations['copyObject']['exception'])) {
            $exception = $this->expectations['copyObject']['exception'];
            if ($exception instanceof \Throwable) {
                throw $exception;
            }
        }

        $return = $this->expectations['copyObject']['return'] ?? null;

        return $return instanceof CopyObjectOutput ? $return : new CopyObjectOutput(createMockResponse());
    }
}
