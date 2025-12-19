<?php

declare(strict_types=1);

namespace Tourze\AwsS3StorageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AwsS3StorageBundle\Exception\S3Exception;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(S3Exception::class)]
final class S3ExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromRuntimeException(): void
    {
        // Act
        $exception = new S3Exception('Test message');

        // Assert
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithMessageShouldReturnMessage(): void
    {
        // Arrange
        $message = 'S3 operation failed';

        // Act
        $exception = new S3Exception($message);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithMessageAndCodeShouldReturnBoth(): void
    {
        // Arrange
        $message = 'S3 operation failed';
        $code = 500;

        // Act
        $exception = new S3Exception($message, $code);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPreviousShouldChainExceptions(): void
    {
        // Arrange
        $message = 'S3 operation failed';
        $code = 500;
        $previous = new \RuntimeException('Original error');

        // Act
        $exception = new S3Exception($message, $code, $previous);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithEmptyMessageShouldAcceptEmptyString(): void
    {
        // Act
        $exception = new S3Exception('');

        // Assert
        $this->assertEquals('', $exception->getMessage());
    }

    public function testExceptionWithZeroCodeShouldAcceptZero(): void
    {
        // Act
        $exception = new S3Exception('Test message', 0);

        // Assert
        $this->assertEquals(0, $exception->getCode());
    }

    public function testExceptionWithNegativeCodeShouldAcceptNegativeValue(): void
    {
        // Act
        $exception = new S3Exception('Test message', -1);

        // Assert
        $this->assertEquals(-1, $exception->getCode());
    }

    public function testExceptionCanBeThrown(): void
    {
        // Arrange
        $message = 'S3 operation failed';

        // Act & Assert
        $this->expectException(S3Exception::class);
        $this->expectExceptionMessage($message);

        throw new S3Exception($message);
    }

    public function testExceptionCanBeCaught(): void
    {
        // Arrange
        $message = 'S3 operation failed';
        $caught = false;

        try {
            // Act
            throw new S3Exception($message);
        } catch (S3Exception $e) {
            $caught = true;
            $caughtMessage = $e->getMessage();
        }

        // Assert
        $this->assertTrue($caught);
        $this->assertEquals($message, $caughtMessage);
    }

    public function testExceptionCanBeCaughtAsRuntimeException(): void
    {
        // Arrange
        $message = 'S3 operation failed';
        $caught = false;

        try {
            // Act
            throw new S3Exception($message);
        } catch (\RuntimeException $e) {
            $caught = true;
            $caughtMessage = $e->getMessage();
        }

        // Assert
        $this->assertTrue($caught);
        $this->assertEquals($message, $caughtMessage);
    }

    public function testExceptionWithLongMessageShouldReturnFullMessage(): void
    {
        // Arrange
        $message = str_repeat('This is a very long error message that should be preserved in its entirety. ', 10);

        // Act
        $exception = new S3Exception($message);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
        $this->assertGreaterThan(100, strlen($exception->getMessage()));
    }

    public function testExceptionWithSpecialCharactersShouldPreserveCharacters(): void
    {
        // Arrange
        $message = 'Error with special chars: àáâãäåæçèéêë ñ 中文 🚀';

        // Act
        $exception = new S3Exception($message);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testMultipleExceptionsCanBeCreated(): void
    {
        // Act
        $exception1 = new S3Exception('First error', 1);
        $exception2 = new S3Exception('Second error', 2);

        // Assert
        $this->assertNotSame($exception1, $exception2);
        $this->assertEquals('First error', $exception1->getMessage());
        $this->assertEquals('Second error', $exception2->getMessage());
        $this->assertEquals(1, $exception1->getCode());
        $this->assertEquals(2, $exception2->getCode());
    }
}
