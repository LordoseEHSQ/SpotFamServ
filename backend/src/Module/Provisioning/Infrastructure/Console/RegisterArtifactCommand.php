<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Infrastructure\Console;

use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Domain\FlashArtifact;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Registriert (oder aktualisiert) ein Firmware-Artefakt in der Datenbank.
 *
 * Verwendung (manuell oder durch CI):
 *   php bin/console app:provisioning:register-artifact \
 *     --board=esp32-wroom-32 --channel=stable --version=1.2.3 \
 *     --file=spotfam_reader_1.2.3.bin --expected-chip=ESP32-D0WD-V3
 *
 * Die Datei muss unter FIRMWARE_DIR liegen (Default: {project_dir}/var/firmware).
 * CI trägt nach jedem erfolgreichen Build neue Artefakte über diesen Command ein.
 *
 * SICHERHEIT (D-025): Kein freier Upload. Nur bereits auf dem Dateisystem vorliegende
 * Binaries werden registriert. Path-Traversal wird durch Dateinamen-Validierung verhindert.
 */
#[AsCommand(
    name: 'app:provisioning:register-artifact',
    description: 'Registriert ein Firmware-Artefakt (board/channel/version) in der Datenbank.',
)]
final class RegisterArtifactCommand extends Command
{
    public function __construct(
        private readonly FlashArtifactRepositoryInterface $artifacts,
        private readonly string $firmwareDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('board', null, InputOption::VALUE_REQUIRED, 'Board-Bezeichner (z.B. esp32-wroom-32)')
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Release-Kanal (z.B. stable, beta)')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Versionsnummer (z.B. 1.2.3)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Dateiname relativ zu FIRMWARE_DIR (kein Pfad, nur Dateiname)')
            ->addOption('expected-chip', null, InputOption::VALUE_REQUIRED, 'Erwarteter Chip-Bezeichner (z.B. ESP32-D0WD-V3)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $board        = trim((string) $input->getOption('board'));
        $channel      = trim((string) $input->getOption('channel'));
        $version      = trim((string) $input->getOption('version'));
        $filename     = trim((string) $input->getOption('file'));
        $expectedChip = trim((string) $input->getOption('expected-chip'));

        if ($board === '' || $channel === '' || $version === '' || $filename === '' || $expectedChip === '') {
            $io->error('Alle Optionen --board, --channel, --version, --file und --expected-chip sind Pflicht.');
            return Command::FAILURE;
        }

        // Path-Traversal-Schutz: Dateiname darf kein / oder .. enthalten
        if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
            $io->error('Der Dateiname darf kein / , \\ oder .. enthalten (kein relativer Pfad erlaubt).');
            return Command::FAILURE;
        }

        $absolutePath = rtrim($this->firmwareDir, '/') . '/' . $filename;

        if (!is_file($absolutePath)) {
            $io->error(sprintf('Datei nicht gefunden: %s', $absolutePath));
            return Command::FAILURE;
        }

        $sha256    = hash_file('sha256', $absolutePath);
        $sizeBytes = filesize($absolutePath);

        if ($sha256 === false) {
            $io->error('SHA-256-Berechnung fehlgeschlagen.');
            return Command::FAILURE;
        }

        if ($sizeBytes === false) {
            $io->error('Dateigröße konnte nicht ermittelt werden.');
            return Command::FAILURE;
        }

        $existing = $this->artifacts->findByBoardChannelVersion($board, $channel, $version);

        if ($existing !== null) {
            $existing->updateContent($filename, $sha256, $sizeBytes);
            $this->artifacts->save($existing);
            $io->success(sprintf(
                'Artefakt aktualisiert: %s/%s/%s → %s (SHA-256: %s, %d Bytes)',
                $board, $channel, $version, $filename, $sha256, $sizeBytes,
            ));
            return Command::SUCCESS;
        }

        $artifact = new FlashArtifact($board, $channel, $version, $filename, $sha256, $expectedChip, $sizeBytes);
        $this->artifacts->save($artifact);

        $io->success(sprintf(
            'Artefakt registriert: %s/%s/%s → %s (SHA-256: %s, %d Bytes, ID: %s)',
            $board, $channel, $version, $filename, $sha256, $sizeBytes, $artifact->getId(),
        ));

        return Command::SUCCESS;
    }
}
