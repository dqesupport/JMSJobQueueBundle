<?php

namespace JMS\JobQueueBundle\Entity\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Хранит сериализованный {@see FlattenException} (стек-трейс задачи) в BLOB.
 */
class SafeObjectType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return serialize($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = is_resource($value) ? stream_get_contents($value) : $value;

        set_error_handler(function (int $code, string $message) use ($value): bool {
            throw SerializationFailed::new($value, 'jms_job_safe_object', $message);
        });

        try {
            return unserialize($value);
        } finally {
            restore_error_handler();
        }
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::LARGE_OBJECT;
    }
}
