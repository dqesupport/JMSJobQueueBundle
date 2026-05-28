<?php

namespace JMS\JobQueueBundle\Entity\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

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

        $result = @unserialize($value);
        if ($result === false && $value !== serialize(false)) {
            throw ValueNotConvertible::new($value, 'jms_job_safe_object');
        }

        return $result;
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::LARGE_OBJECT;
    }
}
