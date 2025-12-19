<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AwsS3StorageBundle\Exception\ConfigurationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ConfigurationException::class)]
final class ConfigurationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromInvalidArgumentException(): void
    {
        // Act
        $exception = new ConfigurationException('Test message');

        // Assert
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testExceptionWithMessageShouldReturnMessage(): void
    {
        // Arrange
        $message = 'Configuration is invalid';

        // Act
        $exception = new ConfigurationException($message);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithMessageAndCodeShouldReturnBoth(): void
    {
        // Arrange
        $message = 'Configuration is invalid';
        $code = 400;

        // Act
        $exception = new ConfigurationException($message, $code);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPreviousShouldChainExceptions(): void
    {
        // Arrange
        $message = 'Configuration is invalid';
        $code = 400;
        $previous = new \InvalidArgumentException('Original validation error');

        // Act
        $exception = new ConfigurationException($message, $code, $previous);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithEmptyMessageShouldAcceptEmptyString(): void
    {
        // Act
        $exception = new ConfigurationException('');

        // Assert
        $this->assertEquals('', $exception->getMessage());
    }

    public function testExceptionWithZeroCodeShouldAcceptZero(): void
    {
        // Act
        $exception = new ConfigurationException('Test message', 0);

        // Assert
        $this->assertEquals(0, $exception->getCode());
    }

    public function testExceptionCanBeThrown(): void
    {
        // Arrange
        $message = 'Bucket name cannot be empty';

        // Act & Assert
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        throw new ConfigurationException($message);
    }

    public function testExceptionCanBeCaught(): void
    {
        // Arrange
        $message = 'Bucket name cannot be empty';
        $caught = false;

        try {
            // Act
            throw new ConfigurationException($message);
        } catch (ConfigurationException $e) {
            $caught = true;
            $caughtMessage = $e->getMessage();
        }

        // Assert
        $this->assertTrue($caught);
        $this->assertEquals($message, $caughtMessage);
    }

    public function testExceptionCanBeCaughtAsInvalidArgumentException(): void
    {
        // Arrange
        $message = 'Invalid configuration parameter';
        $caught = false;

        try {
            // Act
            throw new ConfigurationException($message);
        } catch (\InvalidArgumentException $e) {
            $caught = true;
            $caughtMessage = $e->getMessage();
        }

        // Assert
        $this->assertTrue($caught);
        $this->assertEquals($message, $caughtMessage);
    }

    public function testExceptionWithTypicalConfigurationErrorMessages(): void
    {
        // Arrange
        $messages = [
            'Bucket name cannot be empty',
            'Access key ID is required',
            'Secret access key is required',
            'Invalid region specified',
            'Invalid endpoint URL format',
        ];

        foreach ($messages as $message) {
            // Act
            $exception = new ConfigurationException($message);

            // Assert
            $this->assertEquals($message, $exception->getMessage());
            $this->assertInstanceOf(ConfigurationException::class, $exception);
        }
    }

    public function testExceptionWithLongMessageShouldReturnFullMessage(): void
    {
        // Arrange
        $message = 'The provided configuration contains multiple errors: ' .
                   'bucket name is empty, access key ID is missing, ' .
                   'secret access key is not provided, and the region ' .
                   'parameter is invalid. Please check your configuration.';

        // Act
        $exception = new ConfigurationException($message);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
        $this->assertGreaterThan(100, strlen($exception->getMessage()));
    }

    public function testMultipleExceptionsCanBeCreatedWithDifferentMessages(): void
    {
        // Act
        $exception1 = new ConfigurationException('Bucket name is required', 1001);
        $exception2 = new ConfigurationException('Access key is invalid', 1002);

        // Assert
        $this->assertNotSame($exception1, $exception2);
        $this->assertEquals('Bucket name is required', $exception1->getMessage());
        $this->assertEquals('Access key is invalid', $exception2->getMessage());
        $this->assertEquals(1001, $exception1->getCode());
        $this->assertEquals(1002, $exception2->getCode());
    }
}
