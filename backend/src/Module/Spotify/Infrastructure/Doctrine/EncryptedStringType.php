<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Doctrine;

use App\Module\Spotify\Application\Port\TokenEncryptionInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine type that encrypts on DB write and decrypts on DB read.
 * Entity always sees plaintext in PHP.
 */
final class EncryptedStringType extends Type
{
    public const NAME = 'spotify_encrypted_string';

    private static ?TokenEncryptionInterface $encryptor = null;

    public static function setEncryptor(TokenEncryptionInterface $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (self::$encryptor === null) {
            throw new \RuntimeException('EncryptedStringType encryptor not set.');
        }
        return self::$encryptor->decrypt((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (self::$encryptor === null) {
            throw new \RuntimeException('EncryptedStringType encryptor not set.');
        }
        return self::$encryptor->encrypt((string) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
