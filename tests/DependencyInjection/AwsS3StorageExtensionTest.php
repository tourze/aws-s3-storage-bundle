<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\AwsS3StorageBundle\DependencyInjection\AwsS3StorageExtension;
use Tourze\AwsS3StorageBundle\Factory\FilesystemFactoryDecorator;
use Tourze\AwsS3StorageBundle\Factory\S3AdapterFactory;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(AwsS3StorageExtension::class)]
final class AwsS3StorageExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private AwsS3StorageExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new AwsS3StorageExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testLoadShouldLoadServices(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition(S3AdapterFactory::class));
        $this->assertTrue($this->container->hasDefinition(FilesystemFactoryDecorator::class));
    }

    public function testLoadShouldConfigureServices(): void
    {
        $this->extension->load([], $this->container);

        $factoryDefinition = $this->container->getDefinition(S3AdapterFactory::class);
        $this->assertTrue($factoryDefinition->isPublic());
    }
}
