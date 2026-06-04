<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Domain\FlashArtifact;

/**
 * UseCase: Firmware-Artefakt registrieren oder aktualisieren.
 *
 * Wird sowohl vom Console-Command (app:provisioning:register-artifact)
 * als auch vom HTTP-Upload-Endpunkt (POST /api/v1/provisioning/artifacts) genutzt.
 *
 * Voraussetzungen des Callers:
 * - $filename ist bereits validiert (kein '/', '\\', '..', Null-Byte).
 * - $absolutePath zeigt auf eine existierende, lesbare Datei.
 */
final class RegisterArtifact
{
    public function __construct(
        private readonly FlashArtifactRepositoryInterface $artifacts,
    ) {
    }

    /**
     * @throws ProvisioningException bei SHA-256- oder Größen-Fehler
     */
    public function __invoke(
        string $board,
        string $channel,
        string $version,
        string $filename,
        string $expectedChip,
        string $absolutePath,
    ): FlashArtifact {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw ProvisioningException::internalError('Artefakt-Datei nicht lesbar.');
        }

        $sha256 = hash_file('sha256', $absolutePath);
        if ($sha256 === false) {
            throw ProvisioningException::internalError('SHA-256-Berechnung fehlgeschlagen.');
        }

        $sizeBytes = filesize($absolutePath);
        if ($sizeBytes === false) {
            throw ProvisioningException::internalError('Dateigröße konnte nicht ermittelt werden.');
        }

        $existing = $this->artifacts->findByBoardChannelVersion($board, $channel, $version);

        if ($existing !== null) {
            $existing->updateContent($filename, $sha256, $sizeBytes);
            $this->artifacts->save($existing);
            return $existing;
        }

        $artifact = new FlashArtifact($board, $channel, $version, $filename, $sha256, $expectedChip, $sizeBytes);
        $this->artifacts->save($artifact);

        return $artifact;
    }
}
