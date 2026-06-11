<?php

namespace JMS\JobQueueBundle\Entity\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Хранит сериализованный {@see FlattenException} (стек-трейс задачи) в BLOB.
 *
 * Тип "безопасный" в том смысле, что при чтении он никогда не роняет
 * гидрацию Job: если данные битые или сериализованы под прежним namespace
 * (что бывает после переездов классов между версиями Symfony), вместо
 * исключения возвращается null — стек-трейс лишь отладочная информация.
 */
class SafeObjectType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return serialize($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $result = @unserialize($value, ['allowed_classes' => [FlattenException::class]]);

        // false  — битая/неполная строка либо отсутствие данных (например, 'N;')
        // __PHP_Incomplete_Class — объект сериализован под классом, которого
        //                          больше нет (старый namespace FlattenException)
        if ($result === false || $result instanceof \__PHP_Incomplete_Class) {
            return null;
        }

        return $result;
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::LARGE_OBJECT;
    }
}
