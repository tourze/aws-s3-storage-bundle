<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\AwsS3StorageBundle\AwsS3StorageBundle;
use Tourze\FileStorageBundle\FileStorageBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(AwsS3StorageBundle::class)]
#[RunTestsInSeparateProcesses]
class AwsS3StorageBundleTest extends AbstractBundleTestCase
{
    public function testGetBundleDependenciesReturnsCorrectDependencies(): void
    {
        // Act
        $dependencies = AwsS3StorageBundle::getBundleDependencies();

        // Assert
        $expected = [
            DoctrineBundle::class => ['all' => true],
            FileStorageBundle::class => ['all' => true],
        ];

        $this->assertEquals($expected, $dependencies);
        $this->assertCount(2, $dependencies, 'Should have exactly 2 dependencies');
    }

    public function testGetBundleDependenciesReturnsConsistentResult(): void
    {
        // Act - Multiple calls should return same result
        $dependencies1 = AwsS3StorageBundle::getBundleDependencies();
        $dependencies2 = AwsS3StorageBundle::getBundleDependencies();

        // Assert
        $this->assertEquals($dependencies1, $dependencies2);
    }

    /**
     * @param array<string, bool> $config
     */
    #[TestWith([DoctrineBundle::class, ['all' => true]])]
    #[TestWith([FileStorageBundle::class, ['all' => true]])]
    public function testEachBundleDependencyHasCorrectConfiguration(string $bundleClass, array $config): void
    {
        // Arrange - Get actual dependencies
        $dependencies = AwsS3StorageBundle::getBundleDependencies();

        // Assert - Verify dependency exists and has correct config
        $this->assertArrayHasKey($bundleClass, $dependencies);
        $this->assertEquals($config, $dependencies[$bundleClass]);
        $this->assertArrayHasKey('all', $config);
        $this->assertTrue($config['all']);
    }
}
