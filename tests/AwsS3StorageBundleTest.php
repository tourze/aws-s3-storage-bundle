<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AwsS3StorageBundle\AwsS3StorageBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(AwsS3StorageBundle::class)]
#[RunTestsInSeparateProcesses]
class AwsS3StorageBundleTest extends AbstractBundleTestCase
{
}
