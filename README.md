# AWS S3 Storage Bundle

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle that provides AWS S3 file storage integration using `async-aws/s3` for the FileStorageBundle.

## Features

- Seamless integration with FileStorageBundle
- Uses async-aws/s3 for high-performance S3 operations
- Automatic fallback to local storage when S3 is not configured
- Support for custom S3-compatible endpoints (MinIO, etc.)
- Environment-based configuration
- Comprehensive test coverage

## Installation

```bash
composer require tourze/aws-s3-storage-bundle
```

Add the bundle to your `bundles.php`:

```php
// config/bundles.php
return [
    // ... other bundles
    Tourze\AwsS3StorageBundle\AwsS3StorageBundle::class => ['all' => true],
];
```

## Configuration

Configure AWS S3 storage using environment variables:

```bash
# Required configuration
AWS_S3_BUCKET=your-bucket-name
AWS_S3_REGION=us-east-1

# Optional authentication (uses IAM roles if not provided)
AWS_S3_ACCESS_KEY_ID=your-access-key
AWS_S3_SECRET_ACCESS_KEY=your-secret-key

# Optional configuration
AWS_S3_PREFIX=uploads/                    # File path prefix
AWS_S3_ENDPOINT=https://s3.example.com    # Custom endpoint (for MinIO, etc.)
AWS_S3_USE_PATH_STYLE_ENDPOINT=true       # Use path-style URLs (required for MinIO)
```

### Environment Variables Reference

| Variable | Required | Description | Default |
|----------|----------|-------------|---------|
| `AWS_S3_BUCKET` | Yes | S3 bucket name | - |
| `AWS_S3_REGION` | Yes | AWS region | - |
| `AWS_S3_ACCESS_KEY_ID` | No | Access key ID (uses IAM if not set) | - |
| `AWS_S3_SECRET_ACCESS_KEY` | No | Secret access key | - |
| `AWS_S3_PREFIX` | No | File path prefix | `` |
| `AWS_S3_ENDPOINT` | No | Custom S3 endpoint | - |
| `AWS_S3_USE_PATH_STYLE_ENDPOINT` | No | Use path-style URLs | `false` |

## Usage

Once configured, the bundle automatically decorates the FileStorageBundle's filesystem factory. No additional code changes are required.

```php
// FileStorageBundle will automatically use S3 when configured
$fileService->uploadFile($uploadedFile, $user);
```

## MinIO Support

For MinIO or other S3-compatible services:

```bash
AWS_S3_BUCKET=my-bucket
AWS_S3_REGION=us-east-1
AWS_S3_ACCESS_KEY_ID=minioaccesskey
AWS_S3_SECRET_ACCESS_KEY=miniosecretkey
AWS_S3_ENDPOINT=http://localhost:9000
AWS_S3_USE_PATH_STYLE_ENDPOINT=true
```

## Automatic Fallback

The bundle automatically detects S3 configuration:

- **S3 configured**: Uses AWS S3 for file operations
- **S3 not configured**: Falls back to local file storage

This allows for seamless development and production deployment.

## Testing

Run the test suite:

```bash
vendor/bin/phpunit
```

## Architecture

The bundle uses a decorator pattern to extend FileStorageBundle:

1. `FilesystemFactoryDecorator` checks for S3 configuration
2. If S3 is configured, creates an S3 filesystem using `async-aws/s3`
3. If not configured, delegates to the original filesystem factory

## Requirements

- PHP 8.2+
- Symfony 7.3+
- FileStorageBundle
- async-aws/s3 ^2.1
- league/flysystem ^3.10
- league/flysystem-async-aws-s3 ^3.10

## License

MIT License. See [LICENSE](LICENSE) file for details.