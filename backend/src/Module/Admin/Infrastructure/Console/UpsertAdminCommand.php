<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Console;

use App\Module\Admin\Application\Port\AdminUserRepositoryInterface;
use App\Module\Admin\Domain\AdminUser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Legt einen Admin-User an oder aktualisiert sein Passwort (idempotent).
 *
 * Verwendung:
 *   php bin/console app:admin:upsert --username=admin --password=secret
 *   ADMIN_USERNAME=admin ADMIN_PASSWORD=secret php bin/console app:admin:upsert
 */
#[AsCommand(
    name: 'app:admin:upsert',
    description: 'Admin-User anlegen oder Passwort aktualisieren (idempotent).',
)]
final class UpsertAdminCommand extends Command
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $repository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly string $defaultUsername = '',
        private readonly string $defaultPassword = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Admin-Benutzername (oder Env ADMIN_USERNAME)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin-Passwort (Klartext; wird gehasht; oder Env ADMIN_PASSWORD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = trim((string) ($input->getOption('username') ?: $this->defaultUsername));
        $password = trim((string) ($input->getOption('password') ?: $this->defaultPassword));

        if ($username === '') {
            $io->error('Username fehlt. Nutze --username oder Env ADMIN_USERNAME.');
            return Command::FAILURE;
        }
        if ($password === '') {
            $io->error('Passwort fehlt. Nutze --password oder Env ADMIN_PASSWORD.');
            return Command::FAILURE;
        }

        $existing = $this->repository->findByUsername($username);

        if ($existing !== null) {
            $hashed = $this->hasher->hashPassword($existing, $password);
            $existing->setPassword($hashed);
            $this->repository->save($existing);
            $io->success(sprintf('Passwort für Admin "%s" wurde aktualisiert.', $username));
            return Command::SUCCESS;
        }

        $tempUser = new AdminUser($username, '');
        $hashed = $this->hasher->hashPassword($tempUser, $password);
        $user = new AdminUser($username, $hashed);
        $this->repository->save($user);

        $io->success(sprintf('Admin-User "%s" wurde angelegt.', $username));
        return Command::SUCCESS;
    }
}
