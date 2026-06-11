<?php

namespace JMS\JobQueueBundle\Tests\Entity\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use JMS\JobQueueBundle\Entity\Type\SafeObjectType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class SafeObjectTypeTest extends TestCase
{
    private SafeObjectType $type;

    /** @var AbstractPlatform&\PHPUnit\Framework\MockObject\MockObject */
    private $platform;

    protected function setUp(): void
    {
        $this->type = new SafeObjectType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testConvertToDatabaseValueSerializes()
    {
        $value = FlattenException::createFromThrowable(new \RuntimeException('boom'));

        $this->assertSame(serialize($value), $this->type->convertToDatabaseValue($value, $this->platform));
    }

    public function testConvertToDatabaseValueWithNullStillSerializes()
    {
        // Поведение оригинального ObjectType: null тоже сериализуется ('N;').
        $this->assertSame(serialize(null), $this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testRoundTrip()
    {
        $value = FlattenException::createFromThrowable(new \RuntimeException('boom'));

        $db = $this->type->convertToDatabaseValue($value, $this->platform);
        $php = $this->type->convertToPHPValue($db, $this->platform);

        $this->assertInstanceOf(FlattenException::class, $php);
        $this->assertEquals($value, $php);
        $this->assertSame('boom', $php->getMessage());
    }

    public function testConvertToPHPValueWithNullReturnsNull()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPValueReadsResourceStreams()
    {
        $value = FlattenException::createFromThrowable(new \RuntimeException('boom'));

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, serialize($value));
        rewind($resource);

        $php = $this->type->convertToPHPValue($resource, $this->platform);

        $this->assertInstanceOf(FlattenException::class, $php);
        $this->assertEquals($value, $php);
    }

    public function testConvertToPHPValueThrowsOnMalformedData()
    {
        $this->expectException(SerializationFailed::class);

        $this->type->convertToPHPValue('this-is-not-serialized', $this->platform);
    }

    public function testConvertToPHPValueRestoresErrorHandlerAfterFailure()
    {
        $sentinel = static function (): void {};
        set_error_handler($sentinel);

        try {
            try {
                $this->type->convertToPHPValue('this-is-not-serialized', $this->platform);
                $this->fail('Expected SerializationFailed to be thrown.');
            } catch (SerializationFailed $e) {
                // Внутренний обработчик ошибок должен быть снят (finally),
                // а наш sentinel — снова активным.
                $this->assertSame($sentinel, set_error_handler(static function (): void {}));
                restore_error_handler();
            }
        } finally {
            restore_error_handler();
        }
    }

    public function testGetSQLDeclarationUsesBlob()
    {
        $this->platform->expects($this->once())
            ->method('getBlobTypeDeclarationSQL')
            ->with(['name' => 'stackTrace'])
            ->willReturn('BLOB');

        $this->assertSame('BLOB', $this->type->getSQLDeclaration(['name' => 'stackTrace'], $this->platform));
    }

    public function testGetBindingTypeIsLargeObject()
    {
        $this->assertSame(ParameterType::LARGE_OBJECT, $this->type->getBindingType());
    }
}
