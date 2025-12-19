<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\FileStorageBundle\FileStorageBundle;

final class AwsS3StorageBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            FileStorageBundle::class => ['all' => true],
        ];
    }
}
